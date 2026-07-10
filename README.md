# Climbing Judge AI demo

Egyszeru Docker-alapu Laravel demo sportmaszo / boulder versenybiroi lapok AI-alapu feldolgozasara.

A felhasznalo Google OAuth segitsegevel bejelentkezik, feltolt egy versenybiroi laprol keszult fotot, a Laravel app HTTP multipart requestben elkuldi a kepet a kulon FastAPI AI service-nek, majd a kapott strukturalt JSON alapjan menti es megjeleniti a javithato sorokat.

## Fő funkciok

- Google OAuth login Laravel Socialite-tal
- Dashboard es versenylap lista
- Kepfeltoltes: jpg, jpeg, png, webp, maximum 8 MB
- AI feldolgozas kulon, allapotmentes FastAPI kontenerben
- Nyers AI JSON megjelenitese
- Javithato Bootstrap tablazat
- Mentes adatbazisba, `reviewed` statuszra allitva
- JSON es CSV export
- Felhasznalonkenti hozzaferes: mindenki csak a sajat lapjait latja

## Kontenerek

- `app`: Laravel + Apache
- `db`: MariaDB
- `ai-service`: FastAPI microservice
- `ollama`: helyi AI modellfuttato service, ha az Ollama provider van kivalasztva

## Mappastruktura

A service-ek mappaszinten is szeparaltak, hogy kulon-kulon egyszeruen masolhatok legyenek:

```text
judge/
├── docker-compose.yml
├── .env.example
├── README.md
└── services/
    ├── app/
    │   ├── Dockerfile
    │   ├── docker-entrypoint.dev.sh
    │   ├── composer.json
    │   ├── app/
    │   ├── config/
    │   ├── database/
    │   ├── public/
    │   ├── resources/
    │   └── routes/
    ├── ai-service/
    │   ├── Dockerfile
    │   ├── main.py
    │   └── requirements.txt
    └── db/
        ├── README.md
        ├── conf.d/
        └── initdb/
```

Root szinten csak az orchestration es a kozos konfiguracio marad. A Laravel kod a `services/app`, a FastAPI kod a `services/ai-service`, a MariaDB opcionális config/init fajljai pedig a `services/db` alatt vannak.

A Laravel app Docker networkon belul ezt hivja:

```text
http://ai-service:8000/analyze
```

## Inditas Dockerrel

1. Masold le az env peldat:

```bash
cp .env.example .env
```

Ez a root `.env` fajl ket helyen is hasznalva van: a Docker Compose ebbol olvassa a portokat es jelszavakat, a Laravel kontener pedig ugyanezt kapja meg `/var/www/html/.env` fajlkent. A `services/app/.env` fajlra nincs szukseg; ne oda masold az ertekeket.

2. Inditsd el a kontenereket:

```bash
docker compose up --build
```

3. Generalj Laravel app kulcsot:

```bash
docker compose exec app php artisan key:generate
```

4. Futtasd ujra a migraciot, ha szukseges:

```bash
docker compose exec app php artisan migrate
```

5. Hozd letre a storage linket, ha az entrypoint meg nem tette meg:

```bash
docker compose exec app php artisan storage:link
```

Az alkalmazas alapertelmezetten itt erheto el:

```text
http://localhost:8080
```

Az AI service kulon is elerheto fejleszteshez:

```text
http://localhost:8000/health
```

## .env pelda

```env
APP_NAME="Climbing Judge AI"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_PORT=8080

DB_DATABASE=climbing_judge
DB_USERNAME=climbing
DB_PASSWORD=secret
DB_ROOT_PASSWORD=rootsecret
DB_FORWARD_PORT=3307

SESSION_DRIVER=database
CACHE_STORE=file
FILESYSTEM_DISK=public

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback

AI_PROVIDER=mock
AI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=qwen3-vl:4b
OLLAMA_TIMEOUT=300
AI_SERVICE_TIMEOUT=330
```

## Google OAuth beallitasa

1. Nyisd meg a Google Cloud Console-t.
2. Hozz letre vagy valassz ki egy projektet.
3. Allitsd be az OAuth consent screent.
4. Hozz letre egy OAuth Client ID-t `Web application` tipussal.
5. Authorized redirect URI:

```text
http://localhost:8080/auth/google/callback
```

6. Masold az adatokat az `.env` fajlba:

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback
```

Ha a kontener mar futott, amikor ezeket kitoltotted, inditsd ujra, majd torold a Laravel konfiguracios cache-et:

```bash
docker compose up -d --force-recreate app
docker compose exec app php artisan optimize:clear
```

Ellenorzes ertekek kiirasa nelkul:

```bash
curl -s -D - -o /dev/null http://localhost:8080/auth/google | grep -q 'client_id=' && echo "Google client_id betoltve"
```

Friss klonozas utan a `storage` es `bootstrap/cache` konyvtarakat a Dockerfile mar build kozben, az entrypoint pedig minden kontenerindulaskor letrehozza. Kezzel nem kell `mkdir` parancsokat futtatni.

## AI provider kivalasztasa

Az `AI_PROVIDER` erteke pontosan egy modot valaszt ki. Nincs automatikus fallback: hianyos vagy hibas konfiguracio eseten a FastAPI es a Laravel feltoltooldala is egyertelmu hibát jelez.

### Mock

```env
AI_PROVIDER=mock
```

Nem kell API-kulcs vagy Ollama-kapcsolat. A szolgaltatas a rogzitett minta JSON-t adja vissza, ezert mock modban nincs valodi kepelemzes.

### OpenAI

```env
AI_PROVIDER=openai
AI_API_KEY=sk-...
OPENAI_MODEL=gpt-4.1-mini
```

Az OpenAI API hasznalata hasznalatalapu koltseggel jar. Az `OPENAI_MODEL` az elsodleges modellvaltozo; az atallas megkonnyitesere a korabbi `AI_MODEL` is tamogatott, ha az `OPENAI_MODEL` ures vagy nincs beallitva.

### Ollama

```env
AI_PROVIDER=ollama
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=qwen3-vl:4b
OLLAMA_TIMEOUT=300
```

Az `OLLAMA_URL` konteneren belul az `ollama` service nevere mutat, nem `localhost`-ra. A hoston a service alapertelmezetten a `http://localhost:11434` cimen erheto el hibakereseshez.

Inditas es a vision modell egyszeri, kezi letoltese:

```bash
docker compose up -d ollama
docker compose exec ollama ollama pull qwen3-vl:4b
docker compose up -d --build
```

A modell elso letoltese internetkapcsolatot igenyel. Utana helyben fut, nincs kepenkenti API-dij, viszont a sajat gep CPU/GPU- es memoria-eroforrasait hasznalja. A modell az `ollama_data` Docker volume-ban megmarad, es kontenerinditaskor nem toltodik le automatikusan.

Az AI service a feltoltott kepet minden valodi providernel ellenorzi, JPEG-re tomoriti es maximum 1600x1600 meretre meretezi. Az Ollama alapertelmezett idokorlatja 300 masodperc, amely az `OLLAMA_TIMEOUT` valtozoval modosithato.

A Laravel `AI_SERVICE_TIMEOUT` erteke legyen nagyobb az `OLLAMA_TIMEOUT` ertekenel, hogy a Laravel ne szakitsa meg hamarabb a kerest. Pelda lassabb, CPU-n futo modellhez:

```env
OLLAMA_TIMEOUT=600
AI_SERVICE_TIMEOUT=660
```

Ezek Compose-kornyezeti valtozok, ezert modositasuk utan az erintett kontenereket ujra kell letrehozni:

```bash
docker compose up -d --no-deps --force-recreate ai-service app
```

Providerallapot ellenorzese (nem ad vissza titkot):

```bash
curl http://localhost:8000/status
```

Az AI service a gyoker `.env` fajlt csak olvashato modban csatolja be, es minden statusz- vagy elemzesi keresnel ujraolvassa. Emiatt az `AI_PROVIDER`, `AI_API_KEY`, `OPENAI_MODEL`, `OLLAMA_URL`, `OLLAMA_MODEL` es `OLLAMA_TIMEOUT` modositasahoz nem kell kontenert ujrainditani. Mentes utan a kovetkezo keres mar az uj beallitast hasznalja. A Laravelhez tartozo `AI_SERVICE_TIMEOUT` modositasakor viszont az `app` kontenert ujra kell letrehozni.

A `/health` csak a FastAPI kontener mukodeset jelzi; a `/status` ellenorzi a kivalasztott provider konfiguraciojat. Ollama modban az elerhetoseget es a modell helyi jelenletet is vizsgalja.

Alaptesztek futtatasa:

```bash
docker compose build ai-service
docker compose run --rm ai-service python -m unittest discover -s tests -v
```

## Route-ok

```text
GET    /login
GET    /auth/google
GET    /auth/google/callback
POST   /logout

GET    /dashboard

GET    /score-sheets
GET    /score-sheets/create
POST   /score-sheets
GET    /score-sheets/{scoreSheet}
GET    /score-sheets/{scoreSheet}/edit
PUT    /score-sheets/{scoreSheet}
GET    /score-sheets/{scoreSheet}/export/json
GET    /score-sheets/{scoreSheet}/export/csv
```

## AI service endpoint

```text
POST /analyze
Content-Type: multipart/form-data
Field: image
```

Pelda valasz:

```json
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
```

## Adatbazis

Migraciok:

- `users`: Google OAuth felhasznalok
- `score_sheets`: feltoltott lap metaadatok, kep utvonal, raw AI JSON, statusz
- `score_sheet_rows`: feldolgozott es javithato tablazatsorok
- `sessions`: Laravel database session tarolas

## Oktatasi megjegyzes

A kod szandekosan egyszeru:

- nincs SPA, csak Blade + Bootstrap
- az AI service nem ir adatbazisba
- a Laravel controller kozvetlenul hivja az AI service-t
- a jogosultsag ellenorzes tulajdonos alapon tortenik
- a mock AI valasz miatt API kulcs nelkul is kiprobalhato
