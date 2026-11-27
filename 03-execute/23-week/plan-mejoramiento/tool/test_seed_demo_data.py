"""Offline tests for the API demonstration-data seeder."""

import unittest
from unittest.mock import patch

from seed_demo_data import (
    ApiClient,
    CATEGORY_NAMES,
    REFERENCE_PREFIX,
    SeedError,
    build_record,
    seed_catalog,
    validate_base_url,
)


class FakeApiClient:
    def __init__(self):
        self.posts = []
        self.get_calls = []

    def request_json(self, method, path, payload=None, query=None):
        if method == "GET" and path == "technical-aid-categories":
            return {
                "items": [
                    {"name": name, "id": f"category-{index}"}
                    for index, name in enumerate(CATEGORY_NAMES, start=1)
                ]
            }

        if method == "GET" and path == "technical-aids":
            self.get_calls.append(query)
            page = int(query["page"])
            if page == 1:
                return {
                    "items": [
                        {"reference": f"{REFERENCE_PREFIX}{index:05d}"}
                        for index in range(1, 51)
                    ],
                    "total": 51,
                }
            return {
                "items": [{"reference": f"{REFERENCE_PREFIX}00051"}],
                "total": 51,
            }

        if method == "POST" and path == "technical-aids":
            self.posts.append(payload)
            return {"technical_aid": {"id": "created"}}

        raise AssertionError(f"Unexpected fake API request: {method} {path}")


class SeedDemoDataTests(unittest.TestCase):
    def test_login_rejects_non_admin_accounts(self):
        client = ApiClient("http://127.0.0.1:8000/api", timeout=1)
        response = {"user": {"role": "manager"}, "token": "not-used"}
        with patch.object(client, "request_json", return_value=response):
            with self.assertRaises(SeedError):
                client.login("manager@example.test", "password")
        self.assertEqual(client.token, "not-used")

    def test_logout_revokes_and_clears_the_in_memory_token(self):
        client = ApiClient("http://127.0.0.1:8000/api", timeout=1)
        client.token = "test-token"
        with patch.object(client, "request_json", return_value={}) as request:
            client.logout()

        request.assert_called_once_with("POST", "logout")
        self.assertIsNone(client.token)

    def test_base_url_allows_local_http_and_remote_https(self):
        self.assertEqual(
            validate_base_url("http://127.0.0.1:8000"),
            "http://127.0.0.1:8000/api",
        )
        self.assertEqual(
            validate_base_url("https://api.example.test/api/"),
            "https://api.example.test/api",
        )

    def test_base_url_rejects_remote_plain_http_and_embedded_credentials(self):
        with self.assertRaises(SeedError):
            validate_base_url("http://api.example.test/api")
        with self.assertRaises(SeedError):
            validate_base_url("https://user:secret@example.test/api")
        with self.assertRaises(SeedError):
            validate_base_url("http://localhost:bad-port/api")
        with self.assertRaises(SeedError):
            validate_base_url("http://localhost/api/extra")

    def test_records_are_reproducible_and_cycle_only_official_categories(self):
        categories = {name: f"id-{name}" for name in CATEGORY_NAMES}
        first = build_record(1, categories)
        repeated_first = build_record(1, categories)
        sixth = build_record(6, categories)

        self.assertEqual(first, repeated_first)
        self.assertEqual(first["category_id"], sixth["category_id"])
        self.assertEqual(first["reference"], f"{REFERENCE_PREFIX}00001")
        self.assertEqual(first["category_id"], categories[CATEGORY_NAMES[0]])
        self.assertEqual(first["total_units"], 1)
        self.assertEqual(len({build_record(i, categories)["category_id"] for i in range(1, 6)}), 5)

    def test_preview_paginates_and_does_not_create_records(self):
        client = FakeApiClient()
        result = seed_catalog(client, count=52, apply=False)

        self.assertEqual(len(client.get_calls), 2)
        self.assertEqual(client.posts, [])
        self.assertEqual(result["existing"], 51)
        self.assertEqual(result["pending"], 1)
        self.assertEqual(result["created"], 0)

    def test_apply_creates_only_records_not_already_present(self):
        client = FakeApiClient()
        result = seed_catalog(client, count=52, apply=True)

        self.assertEqual(len(client.posts), 1)
        self.assertEqual(
            [record["reference"] for record in client.posts],
            [f"{REFERENCE_PREFIX}00052"],
        )
        self.assertEqual(result["created"], 1)
        self.assertEqual(result["failed"], [])


if __name__ == "__main__":
    unittest.main()
