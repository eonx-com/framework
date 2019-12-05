<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions\Stubs;

use EoneoPay\Externals\Translator\Interfaces\TranslatorInterface;

class TranslatorStub implements TranslatorInterface
{
    /**
     * @var string[]
     */
    private $keys;

    /**
     * Constructor.
     *
     * @param string[] $keys
     */
    public function __construct(array $keys)
    {
        $this->keys = $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, ?array $replace = null, ?string $locale = null)
    {
        return $this->keys[$key] ?? $key;
    }

    /**
     * {@inheritdoc}
     */
    public function trans(string $key, ?array $replace = null, ?string $locale = null): string
    {
        return $this->keys[$key] ?? $key;
    }
}
