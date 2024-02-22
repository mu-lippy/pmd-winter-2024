<?php

declare(strict_types = 1);

namespace Drupal\Tests\drupaleasy_repositories\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\drupaleasy_repositories\Traits\RepositoryContentTypeTrait;

/**
 * Test description.
 *
 * @group drupaleasy_repositories
 */
final class AddYmlRepoTest extends BrowserTestBase {
  use RepositoryContentTypeTrait;
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'drupaleasy_repositories',
    'user',
    'link',
    'node',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    // Configure tests to use the yml_remote plugin.
    $config = $this->config(name: 'drupaleasy_repositories.settings');
    $config->set('repositories_plugins', ['yml_remote' => 'yml_remote']);
    $config->save();

    // Create and login as a Drupal user with permission to access the
    // DrupalEasy Repositories Settings page. This is UID=2 because UID=1 is
    // created by
    // web/core/lib/Drupal/Core/Test/FunctionalTestSetupTrait::installParameters().
    // This root user can be accessed via $this->rootUser.
    $admin_user = $this->drupalCreateUser(['configure drupaleasy repositories']);
    $this->drupalLogin($admin_user);
    $this->createRepositoryContentType();

    // Create multivalued Repositories URL field for user profiles.
    FieldStorageConfig::create([
      'field_name' => 'field_repository_url',
      'type' => 'link',
      'entity_type' => 'user',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_repository_url',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Repository URL',
    ])->save();
    // Ensure that the new Repository URL field is visible in the existing
    // user entity form mode.
    /** @var \Drupal\Core\Entity\EntityDisplayRepository $entity_display_repository  */
    $entity_display_repository = \Drupal::service('entity_display.repository');
    $entity_display_repository->getFormDisplay('user', 'user', 'default')
      ->setComponent('field_repository_url', ['type' => 'link_default'])
      ->save();

  }

  /**
   * Test callback.
   *
   * @test
   *
   * Public function testSomething(): void {
   * $admin_user = $this->drupalCreateUser([
   * 'access administration pages',
   *   'administer site configuration',
   *  ]);.
   * $this->drupalLogin($admin_user);
   * $this->drupalGet('admin');
   * $this->assertSession()->elementExists('xpath',
   * '//h1[text() = "Administration"]');}.
   * Test that the settings page can be reached and works as expected.
   *
   * This tests that an admin user can access the settings page, select a
   * plugin to enable, and submit the page successfully.
   *
   * @return void
   *   Returns nothing.
   */
  public function testSettingsPage() : void {
    // Get a handle on the browsing session.
    $session = $this->assertSession();

    // Navigate to the DrupalEasy Repositories Settings page and confirm we
    // can reach it.
    $this->drupalGet('/admin/config/services/repositories');
    // Try this with a 500 status code to see it fail.
    $session->statusCodeEquals(200);

    // Select the "Remote .yml file" checkbox and submit the form.
    $edit = [
      'edit-repositories-plugins-yml-remote' => 'yml_remote',
    ];
    $this->submitForm($edit, 'Save configuration');
    $session->statusCodeEquals(200);
    $session->responseContains('The configuration options have been saved.');
    $session->checkboxChecked('edit-repositories-plugins-yml-remote');
    $session->checkboxNotChecked('edit-repositories-plugins-github');

  }

  /**
   * Test that a yml repo can be added to profile by a user.
   *
   * This tests that a yml-based repo can be added to a user's profile and
   * that a repository node is successfully created upon saving the profile.
   *
   * @test
   */
  public function testAddYmlRepo(): void {

    // Create and login as a Drupal user with permission to access
    // content.
    $user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($user);

    // Get a handle on the browsing session.
    $session = $this->assertSession();

    // Navigate to their edit profile page and confirm we can reach it.
    $this->drupalGet('/user/' . $user->id() . '/edit');
    // Try this with a 500 status code to see it fail.
    $session->statusCodeEquals(200);

    // Get the full path to the test .yml file.
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = \Drupal::service('module_handler');
    $module = $module_handler->getModule('drupaleasy_repositories');
    $module_full_path = \Drupal::request()->getUri() . $module->getPath();

    $edit = [
      'field_repository_url[0][uri]' => $module_full_path . '/tests/assets/batman-repo.yml',
    ];
    $this->submitForm($edit, 'Save');
    $session->statusCodeEquals(200);
    $session->responseContains('The changes have been saved.');
    // We can't check for the following message unless we also have the future
    // drupaleasy_notify module enabled.
    // phpcs:ignore
    // $session->responseContains('The repo named
    // <em class="placeholder">The Batman repository</em> has been created');.
    // Find the new repository node.
    $query = \Drupal::entityQuery('node');
    $query->condition('type', 'repository');
    $results = $query->accessCheck(FALSE)->execute();
    $session->assert(count($results) === 1, 'Either 0 or more than 1 repository nodes were found.');

    $entity_type_manager = \Drupal::entityTypeManager();
    $node_storage = $entity_type_manager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load(reset($results));

    $session->assert($node->field_machine_name->value === 'batman-repo', 'Machine name does not match.');
    $session->assert($node->field_source->value === 'yml_remote', 'Source does not match.');
    $session->assert($node->getTitle() === 'The Batman repository', 'Label does not match.');
    $session->assert($node->field_description->value === 'This is where Batman keeps all his crime-fighting code.', 'Description does not match.');
    $session->assert((int) $node->field_number_of_issues->value === 6, 'Number of issues does not match.');

  }

}
