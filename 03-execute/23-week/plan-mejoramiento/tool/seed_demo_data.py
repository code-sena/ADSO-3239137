#!/usr/bin/env python3
"""Seed reproducible technical-aid catalog records through the Mobility Aids API."""

from __future__ import annotations

import argparse
import getpass
import json
import math
import os
import sys
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlsplit, urlunsplit
from urllib.request import Request, urlopen


CATEGORY_LABELS = {
    "Wheelchairs": "Silla de ruedas",
    "Crutches": "Muletas",
    "Walkers": "Caminador",
    "Canes": "Bastón",
    "Cushions": "Cojín",
}
CATEGORY_NAMES = tuple(CATEGORY_LABELS)
REFERENCE_PREFIX = "MA-DEMO-"
PAGE_SIZE = 50
MAX_RECORDS = 5000


class SeedError(Exception):
    """An expected API, input, or transport error safe to show in the CLI."""


def positive_int(value: str) -> int:
    try:
        number = int(value)
    except ValueError as error:
        raise argparse.ArgumentTypeError("must be an integer") from error

    if not 1 <= number <= MAX_RECORDS:
        raise argparse.ArgumentTypeError(f"must be between 1 and {MAX_RECORDS}")
    return number


def positive_float(value: str) -> float:
    try:
        number = float(value)
    except ValueError as error:
        raise argparse.ArgumentTypeError("must be a number") from error

    if not math.isfinite(number) or number <= 0:
        raise argparse.ArgumentTypeError("must be greater than zero")
    return number


def validate_base_url(base_url: str) -> str:
    """Validate an API URL before credentials are sent to it."""
    try:
        parsed = urlsplit(base_url)
        parsed.port
    except ValueError:
        raise SeedError("API URL is malformed.") from None
    if parsed.scheme not in {"http", "https"} or not parsed.hostname:
        raise SeedError("API URL must start with http:// or https:// and include a host.")
    if parsed.username or parsed.password or parsed.query or parsed.fragment:
        raise SeedError("API URL must not contain credentials, a query, or a fragment.")

    host = parsed.hostname.lower()
    local_hosts = {"localhost", "127.0.0.1", "::1"}
    if parsed.scheme == "http" and host not in local_hosts:
        raise SeedError("Plain HTTP is allowed only for localhost; use HTTPS for remote APIs.")

    path = parsed.path.rstrip("/")
    if path not in {"", "/api"}:
        raise SeedError("API URL path must be empty or /api.")
    path = path or "/api"
    return urlunsplit((parsed.scheme, parsed.netloc, path, "", ""))


def build_record(index: int, category_ids: dict[str, str]) -> dict[str, Any]:
    category_name = CATEGORY_NAMES[(index - 1) % len(CATEGORY_NAMES)]
    sequence = f"{index:05d}"
    label = CATEGORY_LABELS[category_name]
    return {
        "category_id": category_ids[category_name],
        "name": f"{label} de demostración {sequence}",
        "reference": f"{REFERENCE_PREFIX}{sequence}",
        "description": "Registro de demostración para validar búsquedas del catálogo.",
        "total_units": 1,
        "maintenance_units": 0,
    }


class ApiClient:
    """Small JSON client for the API endpoints used by this tool."""

    def __init__(self, base_url: str, timeout: float) -> None:
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.token: str | None = None

    def request_json(
        self,
        method: str,
        path: str,
        payload: dict[str, Any] | None = None,
        query: dict[str, str | int] | None = None,
    ) -> dict[str, Any]:
        url = f"{self.base_url}/{path.lstrip('/')}"
        if query:
            url = f"{url}?{urlencode(query)}"

        headers = {"Accept": "application/json"}
        body = None
        if payload is not None:
            headers["Content-Type"] = "application/json"
            body = json.dumps(payload).encode("utf-8")
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"

        request = Request(url, data=body, headers=headers, method=method)
        try:
            with urlopen(request, timeout=self.timeout) as response:
                response_body = response.read()
        except HTTPError as error:
            raise SeedError(f"API returned HTTP {error.code} for {method} {path}.") from None
        except (TimeoutError, URLError, OSError) as error:
            reason = getattr(error, "reason", error)
            raise SeedError(f"Could not reach the API for {method} {path}: {reason}") from None

        try:
            decoded = json.loads(response_body.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError):
            raise SeedError(f"API returned invalid JSON for {method} {path}.") from None
        if not isinstance(decoded, dict):
            raise SeedError(f"API returned an unexpected response for {method} {path}.")
        return decoded

    def login(self, email: str, password: str) -> None:
        response = self.request_json(
            "POST", "login", {"email": email, "password": password}
        )
        user = response.get("user")
        token = response.get("token")
        if not isinstance(user, dict) or not isinstance(token, str) or not token:
            raise SeedError("Login response did not contain a user and token.")
        self.token = token
        if user.get("role") != "admin":
            raise SeedError("The configured account is not an administrator; no data was created.")

    def logout(self) -> None:
        if not self.token:
            return
        try:
            self.request_json("POST", "logout")
        finally:
            self.token = None


def get_category_ids(client: ApiClient) -> dict[str, str]:
    response = client.request_json("GET", "technical-aid-categories")
    items = response.get("items")
    if not isinstance(items, list):
        raise SeedError("Category endpoint returned an unexpected response.")

    categories = {
        item.get("name"): item.get("id")
        for item in items
        if isinstance(item, dict) and isinstance(item.get("id"), str)
    }
    missing = [name for name in CATEGORY_NAMES if name not in categories]
    if missing:
        names = ", ".join(missing)
        raise SeedError(
            f"Required categories are missing: {names}. "
            "Initialize the approved categories with the Laravel category seeder first."
        )
    return {name: categories[name] for name in CATEGORY_NAMES}


def get_existing_references(client: ApiClient) -> set[str]:
    references: set[str] = set()
    page = 1

    while True:
        response = client.request_json(
            "GET",
            "technical-aids",
            query={"q": REFERENCE_PREFIX, "page": page, "size": PAGE_SIZE},
        )
        items = response.get("items")
        if not isinstance(items, list):
            raise SeedError("Technical-aid endpoint returned an unexpected response.")
        for item in items:
            if isinstance(item, dict) and isinstance(item.get("reference"), str):
                references.add(item["reference"])

        try:
            total = int(response["total"])
        except (KeyError, TypeError, ValueError):
            raise SeedError("Technical-aid endpoint omitted the total record count.") from None
        if not items and page * PAGE_SIZE < total:
            raise SeedError("Technical-aid endpoint returned an incomplete page.")
        if page * PAGE_SIZE >= total:
            break
        page += 1

    return references


def seed_catalog(client: ApiClient, count: int, apply: bool) -> dict[str, Any]:
    category_ids = get_category_ids(client)
    existing = get_existing_references(client)
    pending = [
        build_record(index, category_ids)
        for index in range(1, count + 1)
        if f"{REFERENCE_PREFIX}{index:05d}" not in existing
    ]

    created = 0
    failures: list[str] = []
    if apply:
        for record in pending:
            try:
                response = client.request_json("POST", "technical-aids", record)
                if not isinstance(response.get("technical_aid"), dict):
                    failures.append(record["reference"])
                    continue
                created += 1
            except SeedError as error:
                failures.append(record["reference"])
                print(f"Could not create {record['reference']}: {error}", file=sys.stderr)

    return {
        "existing": len(existing),
        "pending": len(pending),
        "created": created,
        "failed": failures,
    }


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Seed deterministic technical-aid demo records through the Mobility Aids API."
    )
    parser.add_argument(
        "--count",
        required=True,
        type=positive_int,
        help=f"number of deterministic catalog records to ensure (1-{MAX_RECORDS})",
    )
    parser.add_argument(
        "--base-url",
        default="http://127.0.0.1:8000/api",
        help="API base URL, including /api (default: %(default)s)",
    )
    parser.add_argument(
        "--email",
        default=os.environ.get("MOBILITY_ADMIN_EMAIL"),
        help="administrator email; defaults to MOBILITY_ADMIN_EMAIL or a prompt",
    )
    parser.add_argument(
        "--timeout",
        type=positive_float,
        default=15.0,
        help="per-request timeout in seconds (default: %(default)s)",
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="create missing records; without this flag the tool only previews",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv)
    client: ApiClient | None = None
    cleanup_failed = False
    try:
        base_url = validate_base_url(args.base_url)
        email = args.email or input("Administrator email: ").strip()
        if not email:
            raise SeedError("Administrator email is required.")
        password = os.environ.get("MOBILITY_ADMIN_PASSWORD")
        if password is None:
            if not sys.stdin.isatty():
                raise SeedError(
                    "Set MOBILITY_ADMIN_PASSWORD or run the tool in an interactive terminal."
                )
            password = getpass.getpass("Administrator password (input hidden): ")
        if not password:
            raise SeedError("Administrator password is required.")

        client = ApiClient(base_url, args.timeout)
        client.login(email, password)
        result = seed_catalog(client, args.count, args.apply)
    except SeedError as error:
        print(f"Seed failed: {error}", file=sys.stderr)
        return 1
    except (EOFError, KeyboardInterrupt):
        print("Seed cancelled.", file=sys.stderr)
        return 130
    finally:
        if client and client.token:
            try:
                client.logout()
            except SeedError as error:
                cleanup_failed = True
                print(
                    f"Warning: API token could not be revoked with logout: {error}",
                    file=sys.stderr,
                )

    if cleanup_failed:
        return 1

    mode = "apply" if args.apply else "preview"
    print(
        f"Mode: {mode}; requested: {args.count}; existing: {result['existing']}; "
        f"pending: {result['pending']}; created: {result['created']}; "
        f"failed: {len(result['failed'])}."
    )
    if result["failed"]:
        print("Failed references: " + ", ".join(result["failed"]), file=sys.stderr)
        return 1
    if not args.apply:
        print("Preview only. Add --apply to create the missing records.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
