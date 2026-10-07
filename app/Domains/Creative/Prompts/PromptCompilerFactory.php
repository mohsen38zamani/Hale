<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Contracts\PromptCompilerInterface;

/**
 * Resolves the compiler for a `target_ai` key.
 *
 * The map grows as the compilers land; PromptCompilersTest keeps it in step
 * with the catalogue in config/creative.php, so a target that exists for the
 * user can never be missing a compiler (and vice versa). A key the map does
 * not know degrades to the generic compiler instead of failing the request:
 * validation is what rejects unknown targets, this is only the last belt.
 */
class PromptCompilerFactory
{
    /**
     * @var array<string, class-string<PromptCompilerInterface>>
     */
    private const COMPILERS = [
        'generic' => GenericPromptCompiler::class,
        'chatgpt' => ChatGptPromptCompiler::class,
        'claude' => ClaudePromptCompiler::class,
        'deepseek' => DeepSeekPromptCompiler::class,
        'grok' => GrokPromptCompiler::class,
    ];

    public function __construct(private readonly PromptClauses $clauses = new PromptClauses) {}

    /**
     * The shared clause builder, exposed so the engine can sanitize the way
     * the compilers do without instantiating a second one.
     */
    public function clauses(): PromptClauses
    {
        return $this->clauses;
    }

    public function for(?string $key): PromptCompilerInterface
    {
        $normalized = strtolower(trim((string) $key));
        $class = self::COMPILERS[$normalized] ?? null;

        return $class !== null ? new $class($this->clauses) : new GenericPromptCompiler($this->clauses);
    }

    /**
     * The keys this factory can compile, in catalogue order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::COMPILERS);
    }
}
