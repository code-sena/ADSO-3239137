# Mobility Aids Tools

## `seed_demo_data.py`

Creates reproducible technical-aid catalog records through the Laravel API. It is intended for local demonstrations and for preparing the 5,000-record catalog used by the search-performance requirement. It does not call the database directly, create users or loans, delete records, run migrations, or modify application configuration.

The tool uses the existing five category records. Initialize those categories using the Laravel `TechnicalAidCategorySeeder` before running this script. The current API has no category-creation endpoint.

### Requirements

- Python 3.10 or newer; only the Python standard library is used.
- A running Mobility Aids API, normally at `http://127.0.0.1:8000/api`.
- An existing administrator account. The API currently authenticates with email and password.

The tool follows the current unversioned Laravel routes (`/api/login`, `/api/technical-aid-categories`, and `/api/technical-aids`). The target contract documents `/api/v1`; that route mismatch remains an existing project gap.

`tool/.gitignore` excludes Python bytecode generated when the offline tests import the script.

### Preview

From the project root in PowerShell:

```powershell
python tool/seed_demo_data.py --count 50 --email admin@example.test
```

The script prompts for the administrator password without displaying input. Preview is the default and does not create catalog records. It authenticates to read the API and logs out at the end to revoke the temporary API token.

### Create records

After reviewing the preview, add `--apply`:

```powershell
python tool/seed_demo_data.py --count 50 --email admin@example.test --apply
```

The same references are generated on every run (`MA-DEMO-00001` onward). Existing matching records are skipped, so rerunning the command is safe. The script only creates records missing from the requested range; reducing `--count` never deletes anything.

To prepare the 5,000-record search dataset:

```powershell
python tool/seed_demo_data.py --count 5000 --email admin@example.test
python tool/seed_demo_data.py --count 5000 --email admin@example.test --apply
```

### Credentials and remote URLs

The password is requested interactively and is never printed. For automation, the script also accepts `MOBILITY_ADMIN_EMAIL` and `MOBILITY_ADMIN_PASSWORD` from the process environment; avoid saving passwords in command history or tracked files. The API token is kept in memory, is not printed, and is revoked through the API logout endpoint when the run ends. Remote API URLs must use HTTPS; plain HTTP is accepted only for localhost.

The account must have the `admin` role. If category data is missing, the script stops and points to the existing Laravel category seeder. HTTP errors are reported by status without echoing response bodies, credentials, or tokens.

### Offline validation

Run the tests without starting the API or touching a database:

```powershell
python -m unittest discover -s tool -p "test_*.py" -v
```
