<?php

declare(strict_types=1);

namespace Drupal\drupaleasy_repositories\Plugin\DrupaleasyRepositories;

use Drupal\drupaleasy_repositories\DrupaleasyRepositories\DrupaleasyRepositoriesPluginBase;

/**
 * Plugin implementation of the drupaleasy_repositories.
 *
 * @DrupaleasyRepositories(
 *   id = "github",
 *   label = @Translation("GitHub"),
 *   description = @Translation("GitHub.com")
 * )
 */
final class Github extends DrupaleasyRepositoriesPluginBase {

  /**
   * {@inheritdoc}
   */
  public function validate(string $uri): bool {
    $pattern = '|^https://github.com/[a-zA-Z0-9_\-]+/[a-zA-Z0-9_\-]+|';
    return preg_match($pattern, $uri) === 1;
  }

  /**
   * {@inheritdoc}
   */
  public function validateHelpText(): string {
    return 'https://github.com/vendor/name';
  }

  /**
   * {@inheritdoc}
   */
  public function getRepo(string $uri): array {

    return [];
  }

}
