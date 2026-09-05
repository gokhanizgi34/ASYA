<?php

namespace App;

enum IntegrationProvider: string
{
    case GenericRest = 'generic_rest';
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case GoogleGemini = 'google_gemini';
    case Pixabay = 'pixabay';
    case DeepSeek = 'deepseek';
    case Mistral = 'mistral';
    case XAi = 'xai';
    case Groq = 'groq';
    case OpenRouter = 'openrouter';
    case GitHubModels = 'github_models';
    case WordPress = 'wordpress';
    case XTrends = 'x_trends';
    case SocialMedia = 'social_media';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GenericRest => 'Genel REST API',
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic Claude',
            self::GoogleGemini => 'Google Gemini',
            self::Pixabay => 'Pixabay Görsel API',
            self::DeepSeek => 'DeepSeek',
            self::Mistral => 'Mistral AI',
            self::XAi => 'xAI (Grok)',
            self::Groq => 'Groq',
            self::OpenRouter => 'OpenRouter',
            self::GitHubModels => 'GitHub Models',
            self::WordPress => 'WordPress',
            self::XTrends => 'X Gündem (Trends API)',
            self::SocialMedia => 'Sosyal Medya',
            self::Other => 'Diğer',
        };
    }

    public function isAi(): bool
    {
        return in_array($this, [
            self::OpenAi,
            self::Anthropic,
            self::GoogleGemini,
            self::DeepSeek,
            self::Mistral,
            self::XAi,
            self::Groq,
            self::OpenRouter,
        ], true);
    }

    public function usesSimpleSetup(): bool
    {
        return $this->isAi() || in_array($this, [self::XTrends, self::Pixabay], true);
    }

    public function defaultBaseUrl(): ?string
    {
        return match ($this) {
            self::OpenAi => 'https://api.openai.com/v1/models',
            self::Anthropic => 'https://api.anthropic.com/v1/models',
            self::GoogleGemini => 'https://generativelanguage.googleapis.com/v1beta/models',
            self::Pixabay => 'https://pixabay.com/api/',
            self::XTrends => 'https://api.x.com/2/trends/by/woeid',
            self::DeepSeek => 'https://api.deepseek.com/models',
            self::Mistral => 'https://api.mistral.ai/v1/models',
            self::XAi => 'https://api.x.ai/v1/models',
            self::Groq => 'https://api.groq.com/openai/v1/models',
            self::OpenRouter => 'https://openrouter.ai/api/v1/models',
            self::GitHubModels => 'https://models.github.ai/inference',
            default => null,
        };
    }

    public function defaultAuthType(): IntegrationAuthType
    {
        return match ($this) {
            self::Anthropic => IntegrationAuthType::ApiKeyHeader,
            self::GoogleGemini, self::Pixabay => IntegrationAuthType::None,
            default => IntegrationAuthType::Bearer,
        };
    }

    public function defaultApiKeyHeader(): ?string
    {
        return $this === self::Anthropic ? 'x-api-key' : null;
    }

    /** @return array<int, string> */
    public function suggestedModels(): array
    {
        return match ($this) {
            self::OpenAi => ['gpt-5', 'gpt-5-mini', 'gpt-4.1'],
            self::Anthropic => ['claude-sonnet-4-5', 'claude-haiku-4-5'],
            self::GoogleGemini => ['gemini-3.5-flash-lite', 'gemini-2.5-flash-lite', 'gemini-2.5-flash', 'gemini-2.5-pro'],
            self::DeepSeek => ['deepseek-chat', 'deepseek-reasoner'],
            self::Mistral => ['mistral-large-latest', 'mistral-small-latest'],
            self::XAi => ['grok-4', 'grok-4-fast'],
            self::Groq => ['llama-3.3-70b-versatile', 'openai/gpt-oss-120b'],
            self::OpenRouter => ['openai/gpt-5', 'anthropic/claude-sonnet-4.5'],
            self::GitHubModels => ['openai/gpt-4.1-mini', 'meta/llama-3.3-70b-instruct', 'microsoft/phi-4'],
            default => [],
        };
    }
}
