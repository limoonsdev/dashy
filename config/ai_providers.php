<?php

/*
|--------------------------------------------------------------------------
| AI providers
|--------------------------------------------------------------------------
|
| Every entry is OpenAI-compatible unless "native" says otherwise. "color" is
| the brand color used by the badge in the UI. OpenRouter alone exposes 300+
| models through one endpoint, which is why the model field stays free text.
|
*/

return [
    'providers' => [
        ['id' => 'openai', 'name' => 'OpenAI', 'color' => '#10a37f', 'base' => 'https://api.openai.com/v1', 'models' => ['gpt-4o-mini', 'gpt-4o']],
        ['id' => 'openrouter', 'name' => 'OpenRouter', 'color' => '#6467f2', 'base' => 'https://openrouter.ai/api/v1', 'models' => ['openai/gpt-4o-mini', 'anthropic/claude-3.5-sonnet']],
        ['id' => 'anthropic', 'name' => 'Anthropic', 'color' => '#d97757', 'base' => 'https://api.anthropic.com/v1', 'models' => ['claude-3-5-sonnet-latest']],
        ['id' => 'google', 'name' => 'Google Gemini', 'color' => '#4285f4', 'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'models' => ['gemini-1.5-pro']],
        ['id' => 'mistral', 'name' => 'Mistral', 'color' => '#fa520f', 'base' => 'https://api.mistral.ai/v1', 'models' => ['mistral-large-latest']],
        ['id' => 'groq', 'name' => 'Groq', 'color' => '#f55036', 'base' => 'https://api.groq.com/openai/v1', 'models' => ['llama-3.3-70b-versatile']],
        ['id' => 'together', 'name' => 'Together AI', 'color' => '#0f6fff', 'base' => 'https://api.together.xyz/v1', 'models' => ['meta-llama/Llama-3.3-70B-Instruct-Turbo']],
        ['id' => 'deepseek', 'name' => 'DeepSeek', 'color' => '#4d6bfe', 'base' => 'https://api.deepseek.com/v1', 'models' => ['deepseek-chat']],
        ['id' => 'xai', 'name' => 'xAI', 'color' => '#111111', 'base' => 'https://api.x.ai/v1', 'models' => ['grok-2-latest']],
        ['id' => 'perplexity', 'name' => 'Perplexity', 'color' => '#20808d', 'base' => 'https://api.perplexity.ai', 'models' => ['sonar-pro']],
        ['id' => 'cohere', 'name' => 'Cohere', 'color' => '#39594d', 'base' => 'https://api.cohere.ai/compatibility/v1', 'models' => ['command-r-plus']],
        ['id' => 'fireworks', 'name' => 'Fireworks', 'color' => '#ff5c00', 'base' => 'https://api.fireworks.ai/inference/v1', 'models' => ['accounts/fireworks/models/llama-v3p3-70b-instruct']],
        ['id' => 'cerebras', 'name' => 'Cerebras', 'color' => '#f26f21', 'base' => 'https://api.cerebras.ai/v1', 'models' => ['llama3.3-70b']],
        ['id' => 'sambanova', 'name' => 'SambaNova', 'color' => '#ee7624', 'base' => 'https://api.sambanova.ai/v1', 'models' => ['Meta-Llama-3.3-70B-Instruct']],
        ['id' => 'hyperbolic', 'name' => 'Hyperbolic', 'color' => '#00c2a8', 'base' => 'https://api.hyperbolic.xyz/v1', 'models' => ['meta-llama/Llama-3.3-70B-Instruct']],
        ['id' => 'novita', 'name' => 'Novita AI', 'color' => '#ff6b35', 'base' => 'https://api.novita.ai/v3/openai', 'models' => ['meta-llama/llama-3.3-70b-instruct']],
        ['id' => 'nvidia', 'name' => 'NVIDIA NIM', 'color' => '#76b900', 'base' => 'https://integrate.api.nvidia.com/v1', 'models' => ['meta/llama-3.3-70b-instruct']],
        ['id' => 'azure', 'name' => 'Azure OpenAI', 'color' => '#0078d4', 'base' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1', 'models' => ['gpt-4o-mini']],
        ['id' => 'bedrock', 'name' => 'AWS Bedrock', 'color' => '#ff9900', 'base' => 'http://localhost:8080/v1', 'models' => ['anthropic.claude-3-5-sonnet']],
        ['id' => 'vertex', 'name' => 'Vertex AI', 'color' => '#34a853', 'base' => 'https://us-central1-aiplatform.googleapis.com/v1', 'models' => ['gemini-1.5-pro']],
        ['id' => 'ollama', 'name' => 'Ollama', 'color' => '#3d3d3d', 'base' => 'http://localhost:11434/v1', 'models' => ['llama3.2', 'qwen2.5']],
        ['id' => 'lmstudio', 'name' => 'LM Studio', 'color' => '#6b21a8', 'base' => 'http://localhost:1234/v1', 'models' => ['local-model']],
        ['id' => 'vllm', 'name' => 'vLLM', 'color' => '#fbbf24', 'base' => 'http://localhost:8000/v1', 'models' => ['local-model']],
        ['id' => 'llamacpp', 'name' => 'llama.cpp', 'color' => '#7c3aed', 'base' => 'http://localhost:8080/v1', 'models' => ['local-model']],
        ['id' => 'localai', 'name' => 'LocalAI', 'color' => '#0ea5e9', 'base' => 'http://localhost:8080/v1', 'models' => ['gpt-4']],
        ['id' => 'jan', 'name' => 'Jan', 'color' => '#1f2937', 'base' => 'http://localhost:1337/v1', 'models' => ['local-model']],
        ['id' => 'textgen', 'name' => 'text-generation-webui', 'color' => '#9333ea', 'base' => 'http://localhost:5000/v1', 'models' => ['local-model']],
        ['id' => 'openwebui', 'name' => 'Open WebUI', 'color' => '#ff7a45', 'base' => 'http://localhost:3000/api', 'models' => ['local-model']],
        ['id' => 'litellm', 'name' => 'LiteLLM', 'color' => '#22c55e', 'base' => 'http://localhost:4000/v1', 'models' => ['gpt-4o-mini']],
        ['id' => 'portkey', 'name' => 'Portkey', 'color' => '#6366f1', 'base' => 'https://api.portkey.ai/v1', 'models' => ['gpt-4o-mini']],
        ['id' => 'ai21', 'name' => 'AI21', 'color' => '#e91e63', 'base' => 'https://api.ai21.com/studio/v1', 'models' => ['jamba-1.5-large']],
        ['id' => 'aleph', 'name' => 'Aleph Alpha', 'color' => '#0f172a', 'base' => 'https://api.aleph-alpha.com/v1', 'models' => ['luminous-base']],
        ['id' => 'baidu', 'name' => 'Baidu ERNIE', 'color' => '#2932e1', 'base' => 'https://qianfan.baidubce.com/v2', 'models' => ['ernie-4.0']],
        ['id' => 'dashscope', 'name' => 'Alibaba Qwen', 'color' => '#ff6a00', 'base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'models' => ['qwen-max']],
        ['id' => 'zhipu', 'name' => 'Zhipu GLM', 'color' => '#1e3a8a', 'base' => 'https://open.bigmodel.cn/api/paas/v4', 'models' => ['glm-4-plus']],
        ['id' => 'moonshot', 'name' => 'Moonshot', 'color' => '#111827', 'base' => 'https://api.moonshot.cn/v1', 'models' => ['moonshot-v1-8k']],
        ['id' => 'minimax', 'name' => 'MiniMax', 'color' => '#f43f5e', 'base' => 'https://api.minimax.chat/v1', 'models' => ['abab6.5s-chat']],
        ['id' => '01ai', 'name' => '01.AI Yi', 'color' => '#0ea5e9', 'base' => 'https://api.lingyiwanwu.com/v1', 'models' => ['yi-large']],
        ['id' => 'reka', 'name' => 'Reka', 'color' => '#f97316', 'base' => 'https://api.reka.ai/v1', 'models' => ['reka-core']],
        ['id' => 'writer', 'name' => 'Writer Palmyra', 'color' => '#111827', 'base' => 'https://api.writer.com/v1', 'models' => ['palmyra-x-004']],
        ['id' => 'upstage', 'name' => 'Upstage Solar', 'color' => '#7c3aed', 'base' => 'https://api.upstage.ai/v1/solar', 'models' => ['solar-pro']],
        ['id' => 'voyage', 'name' => 'Voyage AI', 'color' => '#2563eb', 'base' => 'https://api.voyageai.com/v1', 'models' => ['voyage-3']],
        ['id' => 'jina', 'name' => 'Jina AI', 'color' => '#ec4899', 'base' => 'https://api.jina.ai/v1', 'models' => ['jina-embeddings-v3']],
        ['id' => 'huggingface', 'name' => 'Hugging Face', 'color' => '#ffd21e', 'base' => 'https://api-inference.huggingface.co/v1', 'models' => ['meta-llama/Llama-3.3-70B-Instruct']],
        ['id' => 'replicate', 'name' => 'Replicate', 'color' => '#111827', 'base' => 'https://api.replicate.com/v1', 'models' => ['meta/llama-3.3-70b-instruct']],
        ['id' => 'octoai', 'name' => 'OctoAI', 'color' => '#6366f1', 'base' => 'https://text.octoai.run/v1', 'models' => ['meta-llama-3.1-70b-instruct']],
        ['id' => 'lepton', 'name' => 'Lepton AI', 'color' => '#14b8a6', 'base' => 'https://llama3-3-70b.lepton.run/api/v1', 'models' => ['llama3-3-70b']],
        ['id' => 'anyscale', 'name' => 'Anyscale', 'color' => '#0ea5e9', 'base' => 'https://api.endpoints.anyscale.com/v1', 'models' => ['meta-llama/Llama-3-70b-chat-hf']],
        ['id' => 'deepinfra', 'name' => 'DeepInfra', 'color' => '#2563eb', 'base' => 'https://api.deepinfra.com/v1/openai', 'models' => ['meta-llama/Llama-3.3-70B-Instruct']],
        ['id' => 'nebius', 'name' => 'Nebius', 'color' => '#0f172a', 'base' => 'https://api.studio.nebius.ai/v1', 'models' => ['meta-llama/Llama-3.3-70B-Instruct']],
        ['id' => 'scaleway', 'name' => 'Scaleway', 'color' => '#4f0599', 'base' => 'https://api.scaleway.ai/v1', 'models' => ['llama-3.3-70b-instruct']],
        ['id' => 'ovh', 'name' => 'OVHcloud AI', 'color' => '#0050d1', 'base' => 'https://oai.endpoints.kepler.ai.cloud.ovh.net/v1', 'models' => ['Meta-Llama-3_3-70B-Instruct']],
        ['id' => 'infomaniak', 'name' => 'Infomaniak AI', 'color' => '#0a5cff', 'base' => 'https://api.infomaniak.com/1/ai', 'models' => ['llama3']],
        ['id' => 'cloudflare', 'name' => 'Cloudflare Workers AI', 'color' => '#f6821f', 'base' => 'https://api.cloudflare.com/client/v4/accounts/ACCOUNT/ai/v1', 'models' => ['@cf/meta/llama-3.1-8b-instruct']],
        ['id' => 'github', 'name' => 'GitHub Models', 'color' => '#24292f', 'base' => 'https://models.inference.ai.azure.com', 'models' => ['gpt-4o-mini']],
        ['id' => 'nous', 'name' => 'Nous Research', 'color' => '#111827', 'base' => 'https://inference-api.nousresearch.com/v1', 'models' => ['Hermes-3-Llama-3.1-70B']],
        ['id' => 'custom', 'name' => 'Custom endpoint', 'color' => '#6b16ed', 'base' => '', 'models' => []],
    ],
];
