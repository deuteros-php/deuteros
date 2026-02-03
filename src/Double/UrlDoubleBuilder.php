<?php

declare(strict_types=1);

namespace Deuteros\Double;

/**
 * Builds callable resolvers for Url double methods.
 *
 * Produces framework-agnostic callable resolvers for Url methods. These
 * resolvers are then wired to PHPUnit mocks or Prophecy doubles by the adapter
 * factories.
 */
final class UrlDoubleBuilder {

  /**
   * Factory for creating GeneratedUrl doubles.
   *
   * @var callable|null
   */
  private mixed $generatedUrlFactory = NULL;

  /**
   * Constructs a UrlDoubleBuilder.
   *
   * @param string $url
   *   The URL string to return from ::toString.
   * @param array<string, mixed> $options
   *   The URL options (e.g., ['absolute' => TRUE]).
   */
  public function __construct(
    private readonly string $url,
    private readonly array $options = [],
  ) {}

  /**
   * Sets the factory for creating GeneratedUrl doubles.
   *
   * @param callable $factory
   *   A callable that accepts (string $url) and returns a GeneratedUrl double.
   */
  public function setGeneratedUrlFactory(callable $factory): void {
    $this->generatedUrlFactory = $factory;
  }

  /**
   * Gets all Url method resolvers.
   *
   * @return array<string, callable>
   *   Resolvers keyed by method name.
   */
  public function getResolvers(): array {
    return [
      'toString' => $this->buildToStringResolver(),
    ];
  }

  /**
   * Builds the ::toString resolver.
   *
   * Returns the URL string when $collect_bubbleable_metadata is FALSE,
   * or a GeneratedUrl double when TRUE. Respects the "absolute" option.
   *
   * @return callable
   *   The resolver callable.
   */
  private function buildToStringResolver(): callable {
    return function (array $context, bool $collectBubbleableMetadata = FALSE): mixed {
      $url = $this->url;

      // Apply absolute option if set.
      if (($this->options['absolute'] ?? FALSE) && !$this->isAbsoluteUrl($url)) {
        $url = 'http://example.com' . $url;
      }

      if ($collectBubbleableMetadata) {
        if ($this->generatedUrlFactory === NULL) {
          throw new \LogicException('GeneratedUrl factory not set. Cannot create GeneratedUrl double.');
        }
        return ($this->generatedUrlFactory)($url);
      }
      return $url;
    };
  }

  /**
   * Checks if a URL is absolute.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if the URL is absolute (has a scheme), FALSE otherwise.
   */
  private function isAbsoluteUrl(string $url): bool {
    return str_contains($url, '://');
  }

  /**
   * Gets the URL string.
   *
   * @return string
   *   The URL string.
   */
  public function getUrl(): string {
    return $this->url;
  }

}
