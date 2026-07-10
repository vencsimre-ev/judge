import base64
import io
import os
import unittest
from unittest.mock import patch

import httpx
from fastapi import HTTPException, UploadFile

import main


PNG = base64.b64decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="
)


class FakeAsyncClient:
    response = None
    error = None

    def __init__(self, **_kwargs):
        pass

    async def __aenter__(self):
        return self

    async def __aexit__(self, *_args):
        return False

    async def post(self, *_args, **_kwargs):
        if self.error:
            raise self.error
        return self.response

    async def get(self, *_args, **_kwargs):
        if self.error:
            raise self.error
        return self.response


class ProviderTests(unittest.IsolatedAsyncioTestCase):
    def tearDown(self):
        FakeAsyncClient.response = None
        FakeAsyncClient.error = None

    async def test_mock_provider_selection(self):
        with patch.dict(os.environ, {"AI_PROVIDER": "mock"}, clear=True):
            result = await main.analyze(UploadFile(io.BytesIO(PNG), filename="sheet.png", headers={"content-type": "image/png"}))
        self.assertEqual(result, main.mock_response())

    async def test_openai_without_key(self):
        with patch.dict(os.environ, {"AI_PROVIDER": "openai"}, clear=True):
            with self.assertRaises(HTTPException) as context:
                await main.analyze(UploadFile(io.BytesIO(PNG), filename="sheet.png", headers={"content-type": "image/png"}))
        self.assertEqual(context.exception.status_code, 503)
        self.assertIn("AI_API_KEY", context.exception.detail)

    async def test_unsupported_provider(self):
        with patch.dict(os.environ, {"AI_PROVIDER": "unknown"}, clear=True):
            with self.assertRaises(HTTPException) as context:
                await main.analyze(UploadFile(io.BytesIO(PNG), filename="sheet.png", headers={"content-type": "image/png"}))
        self.assertEqual(context.exception.status_code, 400)

    async def test_ollama_unreachable(self):
        request = httpx.Request("POST", "http://ollama:11434/api/chat")
        FakeAsyncClient.error = httpx.ConnectError("offline", request=request)
        with patch.object(main.httpx, "AsyncClient", FakeAsyncClient):
            with self.assertRaises(HTTPException) as context:
                await main.analyze_with_ollama(PNG)
        self.assertEqual(context.exception.status_code, 503)

    async def test_ollama_invalid_json_content(self):
        request = httpx.Request("POST", "http://ollama:11434/api/chat")
        FakeAsyncClient.response = httpx.Response(
            200,
            request=request,
            json={"message": {"content": "not-json"}},
        )
        with patch.object(main.httpx, "AsyncClient", FakeAsyncClient):
            with self.assertRaises(HTTPException) as context:
                await main.analyze_with_ollama(PNG)
        self.assertEqual(context.exception.status_code, 502)
        self.assertIn("nem valid JSON", context.exception.detail)

    async def test_status_responses(self):
        with patch.dict(os.environ, {"AI_PROVIDER": "mock"}, clear=True):
            mock_status = await main.provider_status()
        self.assertTrue(mock_status["configured"])
        self.assertEqual(mock_status["provider"], "mock")

        with patch.dict(os.environ, {"AI_PROVIDER": "openai"}, clear=True):
            openai_status = await main.provider_status()
        self.assertFalse(openai_status["configured"])
        self.assertEqual(openai_status["status"], "configuration_error")

        request = httpx.Request("GET", "http://ollama:11434/api/tags")
        FakeAsyncClient.response = httpx.Response(200, request=request, json={"models": []})
        with patch.dict(os.environ, {"AI_PROVIDER": "ollama", "OLLAMA_MODEL": "qwen3-vl:4b"}, clear=True):
            with patch.object(main.httpx, "AsyncClient", FakeAsyncClient):
                ollama_status = await main.provider_status()
        self.assertTrue(ollama_status["service_reachable"])
        self.assertFalse(ollama_status["model_available"])


if __name__ == "__main__":
    unittest.main()
