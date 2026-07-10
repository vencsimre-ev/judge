import base64
import io
import json
import os
from typing import Any

import httpx
from fastapi import FastAPI, File, HTTPException, UploadFile
from openai import OpenAI
from PIL import Image
from dotenv import load_dotenv

app = FastAPI(title="Climbing Judge AI Service")

PROMPT = """Elemezd a feltoltott versenybiroi lap kepet.

A kep egy sportmaszo / boulder versenybiroi lapot tartalmaz.
Olvasd ki a tablazat sorait es add vissza strukturalt JSON formaban.

A probak oszlopban hasznalt jelolesek:
- I = sikertelen proba
- z betu vagy + jel = zona elerese
- T vagy F vagy ehhez hasonlo olyan F ami egy kozepen athuzott nagy T betu = top elerese

Fontos szabalyok:
- A Zone mezo azt jelenti, hogy hanyadik probara erte el eloszor a zonat.
- A Top mezo azt jelenti, hogy hanyadik probara erte el eloszor a topot.
- Nem az osszes zona vagy top darabszamat kell szamolni.
- Csak az elso sikeres zona es az elso sikeres top szamit.
- A probak teljes szama csak ellenorzesre szolgal.
- Ha valaki topot er el, es nincs kulon korabbi zona jeloles, akkor a zona probaja ugyanaz, mint a top probaja, tehat Z1 T1
- Pelda: "+T" jelentese: Zone = 1, Top = 2.
- Pelda: "T" jelentese: Zone = 1, Top = 1.
- Pelda: "II+I" jelentese: Zone = 3, Top = null.
- Pelda: "III" jelentese: Zone = null, Top = null.

A jobb oldali Top es Zone oszlopokban szereplo szamok is probaszamok.
Ha a probak oszlopa es a jobb oldali Top/Zone oszlop ellentmond egymasnak, akkor:
- add vissza mindkettot,
- jelezd a warnings mezoben az elterest,
- ne talalj ki adatot.

Csak valid JSON-t adj vissza.
Ne irj magyarazatot a JSON ele vagy moge.

Elvart JSON forma:
{
  "sheet": {
    "category": null,
    "route": null,
    "judge_name": null,
    "confidence": 0.0
  },
  "rows": [
    {
      "row_number": 1,
      "start_time": null,
      "bib": null,
      "name": null,
      "country": null,
      "attempts_raw": null,
      "attempts_count": null,
      "zone_attempt": null,
      "top_attempt": null,
      "zone_column_value": null,
      "top_column_value": null,
      "confidence": 0.0,
      "warnings": []
    }
  ]
}
"""


def mock_response() -> dict[str, Any]:
    return {
        "sheet": {
            "category": "Demo boulder - noi felnott",
            "route": "B1",
            "judge_name": "Demo Biro",
            "confidence": 0.78,
        },
        "rows": [
            {
                "row_number": 1,
                "start_time": "10:00",
                "bib": "101",
                "name": "Kovacs Anna",
                "country": "HUN",
                "attempts_raw": "+T",
                "attempts_count": 2,
                "zone_attempt": 1,
                "top_attempt": 2,
                "zone_column_value": 1,
                "top_column_value": 2,
                "confidence": 0.92,
                "warnings": [],
            },
            {
                "row_number": 2,
                "start_time": "10:05",
                "bib": "102",
                "name": "Nagy Bela",
                "country": "HUN",
                "attempts_raw": "II+I",
                "attempts_count": 4,
                "zone_attempt": 3,
                "top_attempt": None,
                "zone_column_value": 3,
                "top_column_value": None,
                "confidence": 0.86,
                "warnings": [],
            },
            {
                "row_number": 3,
                "start_time": "10:10",
                "bib": "103",
                "name": "Demo Petra",
                "country": "AUT",
                "attempts_raw": "T",
                "attempts_count": 1,
                "zone_attempt": 1,
                "top_attempt": 1,
                "zone_column_value": 2,
                "top_column_value": 1,
                "confidence": 0.69,
                "warnings": ["zone_column_conflict"],
            },
        ],
    }


def get_provider() -> str:
    dynamic_env_file = os.getenv("DYNAMIC_ENV_FILE")
    if dynamic_env_file:
        load_dotenv(dynamic_env_file, override=True)
    return os.getenv("AI_PROVIDER", "mock").strip().lower()


def get_openai_model() -> str:
    return os.getenv("OPENAI_MODEL") or os.getenv("AI_MODEL") or "gpt-4.1-mini"


def get_ollama_model() -> str:
    return os.getenv("OLLAMA_MODEL", "qwen3-vl:4b")


def get_ollama_url() -> str:
    return os.getenv("OLLAMA_URL", "http://ollama:11434").rstrip("/")


def get_ollama_timeout() -> float:
    try:
        timeout = float(os.getenv("OLLAMA_TIMEOUT", "300"))
        if timeout <= 0:
            raise ValueError
        return timeout
    except ValueError as exc:
        raise HTTPException(
            status_code=503,
            detail="Az OLLAMA_TIMEOUT értékének pozitív számnak kell lennie.",
        ) from exc


def compress_image(image_bytes: bytes) -> bytes:
    try:
        image = Image.open(io.BytesIO(image_bytes))
        image.thumbnail((1600, 1600))
        if image.mode not in ("RGB", "L"):
            image = image.convert("RGB")

        buffer = io.BytesIO()
        image.save(buffer, format="JPEG", quality=82, optimize=True)
        return buffer.getvalue()
    except Exception as exc:
        raise HTTPException(status_code=400, detail="A feltoltott fajl nem ervenyes kep.") from exc


def analyze_with_openai(image_bytes: bytes) -> dict[str, Any]:
    api_key = os.getenv("AI_API_KEY")
    if not api_key:
        raise HTTPException(
            status_code=503,
            detail="Az OpenAI provider aktív, de nincs megadva AI_API_KEY.",
        )

    client = OpenAI(api_key=api_key)
    model = get_openai_model()
    encoded = base64.b64encode(image_bytes).decode("utf-8")

    response = client.chat.completions.create(
        model=model,
        response_format={"type": "json_object"},
        messages=[
            {
                "role": "user",
                "content": [
                    {"type": "text", "text": PROMPT},
                    {
                        "type": "image_url",
                        "image_url": {"url": f"data:image/jpeg;base64,{encoded}"},
                    },
                ],
            }
        ],
    )

    content = response.choices[0].message.content
    if not content:
        raise HTTPException(status_code=502, detail="Az AI provider ures valaszt adott.")

    try:
        return json.loads(content)
    except json.JSONDecodeError as exc:
        raise HTTPException(status_code=502, detail="Az AI provider valasza nem valid JSON.") from exc


async def analyze_with_ollama(image_bytes: bytes) -> dict[str, Any]:
    encoded = base64.b64encode(image_bytes).decode("utf-8")
    payload = {
        "model": get_ollama_model(),
        "messages": [
            {
                "role": "user",
                "content": PROMPT,
                "images": [encoded],
            }
        ],
        "stream": False,
        "format": "json",
        "options": {"temperature": 0},
    }

    try:
        async with httpx.AsyncClient(timeout=get_ollama_timeout()) as client:
            response = await client.post(f"{get_ollama_url()}/api/chat", json=payload)
    except httpx.TimeoutException as exc:
        raise HTTPException(status_code=504, detail="Az Ollama hívás túllépte az időkorlátot.") from exc
    except httpx.RequestError as exc:
        raise HTTPException(status_code=503, detail="Az Ollama service nem érhető el.") from exc

    if response.status_code == 404:
        raise HTTPException(
            status_code=503,
            detail="A konfigurált Ollama modell nincs letöltve.",
        )
    if response.is_error:
        raise HTTPException(
            status_code=502,
            detail=f"Az Ollama service hibával tért vissza (HTTP {response.status_code}).",
        )

    try:
        content = response.json().get("message", {}).get("content")
    except (ValueError, AttributeError) as exc:
        raise HTTPException(status_code=502, detail="Az Ollama hibás választ adott.") from exc

    if not content:
        raise HTTPException(status_code=502, detail="Az Ollama üres választ adott.")

    try:
        return json.loads(content)
    except (json.JSONDecodeError, TypeError) as exc:
        raise HTTPException(status_code=502, detail="Az Ollama válasza nem valid JSON.") from exc


async def ollama_status() -> dict[str, Any]:
    model = get_ollama_model()
    base = {
        "provider": "ollama",
        "model": model,
        "configured": False,
        "service_reachable": False,
        "model_available": False,
    }

    try:
        timeout = min(get_ollama_timeout(), 10.0)
        async with httpx.AsyncClient(timeout=timeout) as client:
            response = await client.get(f"{get_ollama_url()}/api/tags")
            response.raise_for_status()
            models = response.json().get("models", [])
    except HTTPException as exc:
        return {**base, "status": "configuration_error", "message": exc.detail}
    except httpx.TimeoutException:
        return {**base, "status": "unavailable", "message": "Az Ollama állapotlekérése túllépte az időkorlátot."}
    except (httpx.HTTPError, ValueError, AttributeError):
        return {**base, "status": "unavailable", "message": "Az Ollama service nem érhető el."}

    available = any(item.get("name") == model or item.get("model") == model for item in models)
    if not available:
        return {
            **base,
            "status": "configuration_error",
            "service_reachable": True,
            "message": "Az Ollama elérhető, de a konfigurált modell nincs letöltve.",
        }

    return {
        **base,
        "status": "ok",
        "configured": True,
        "service_reachable": True,
        "model_available": True,
        "message": "A helyi Ollama mód aktív.",
    }


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.get("/status")
async def provider_status() -> dict[str, Any]:
    provider = get_provider()
    if provider == "mock":
        return {
            "status": "ok",
            "provider": "mock",
            "model": None,
            "configured": True,
            "message": "Mock mód aktív.",
        }
    if provider == "openai":
        configured = bool(os.getenv("AI_API_KEY"))
        return {
            "status": "ok" if configured else "configuration_error",
            "provider": "openai",
            "model": get_openai_model(),
            "configured": configured,
            "message": (
                "OpenAI mód aktív."
                if configured
                else "Az OpenAI provider aktív, de nincs megadva AI_API_KEY."
            ),
        }
    if provider == "ollama":
        return await ollama_status()
    return {
        "status": "configuration_error",
        "provider": provider,
        "model": None,
        "configured": False,
        "message": f"Nem támogatott AI provider: {provider}",
    }


@app.post("/analyze")
async def analyze(image: UploadFile = File(...)) -> dict[str, Any]:
    if image.content_type not in {"image/jpeg", "image/png", "image/webp"}:
        raise HTTPException(status_code=422, detail="Csak jpg, png vagy webp kep toltheto fel.")

    image_bytes = await image.read()
    compressed = compress_image(image_bytes)

    provider = get_provider()
    if provider == "mock":
        return mock_response()
    if provider == "openai":
        return analyze_with_openai(compressed)
    if provider == "ollama":
        return await analyze_with_ollama(compressed)
    raise HTTPException(status_code=400, detail=f"Nem támogatott AI provider: {provider}")
