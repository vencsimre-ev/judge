import base64
import io
import json
import os
from typing import Any

from fastapi import FastAPI, File, HTTPException, UploadFile
from openai import OpenAI
from PIL import Image

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


def should_mock() -> bool:
    mode = os.getenv("MOCK_MODE", "auto").lower()
    if mode in {"1", "true", "yes", "on"}:
        return True
    if mode in {"0", "false", "no", "off"}:
        return False
    return not bool(os.getenv("AI_API_KEY"))


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
    client = OpenAI(api_key=os.getenv("AI_API_KEY"))
    model = os.getenv("AI_MODEL", "gpt-4.1-mini")
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


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/analyze")
async def analyze(image: UploadFile = File(...)) -> dict[str, Any]:
    if image.content_type not in {"image/jpeg", "image/png", "image/webp"}:
        raise HTTPException(status_code=422, detail="Csak jpg, png vagy webp kep toltheto fel.")

    image_bytes = await image.read()
    compressed = compress_image(image_bytes)

    if should_mock():
        return mock_response()

    provider = os.getenv("AI_PROVIDER", "openai").lower()
    if provider != "openai":
        raise HTTPException(status_code=400, detail=f"Nem tamogatott AI provider: {provider}")

    return analyze_with_openai(compressed)
