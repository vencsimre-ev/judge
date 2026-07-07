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

AI_PROVIDER=openai
AI_API_KEY=
AI_MODEL=gpt-4.1-mini
MOCK_MODE=auto
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

## AI API kulcs es mock mod

Alapertelmezetten a demo mock modban is mukodik. Ha az `AI_API_KEY` ures es a `MOCK_MODE=auto`, akkor a FastAPI service minta JSON-t ad vissza, igy a Laravel felulet azonnal tesztelheto.

Mock mod kenyszeritese:

```env
MOCK_MODE=true
AI_API_KEY=
```

Valodi AI hivas:

```env
MOCK_MODE=false
AI_PROVIDER=openai
AI_API_KEY=sk-...
AI_MODEL=gpt-4.1-mini
```

Az AI service a feltoltott kepet ellenorzi, JPEG-re tomoriti es maximum 1600x1600 meretre meretezi, mielott az AI providernek tovabbitja.

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
