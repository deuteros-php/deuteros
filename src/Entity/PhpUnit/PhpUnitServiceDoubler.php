<?php

declare(strict_types=1);

namespace Deuteros\Entity\PhpUnit;

use Deuteros\Entity\ServiceDoublerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Uuid\UuidInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates service doubles using PHPUnit mocks.
 */
final class PhpUnitServiceDoubler implements ServiceDoublerInterface {

  /**
   * The test case.
   */
  private readonly TestCase $testCase;

  /**
   * Constructs a PhpUnitServiceDoubler.
   *
   * @param \PHPUnit\Framework\TestCase $testCase
   *   The test case for creating mocks.
   */
  public function __construct(TestCase $testCase) {
    $this->testCase = $testCase;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContainer(
    array $entityTypeConfigs,
    ?ContainerInterface $container = NULL,
  ): ContainerInterface {
    $container ??= new ContainerBuilder();

    // Create and register service doubles.
    $container->set(
      'entity_type.manager',
      $this->createEntityTypeManagerDouble($entityTypeConfigs)
    );

    $container->set(
      'entity_type.bundle.info',
      $this->createBundleInfoDouble($entityTypeConfigs)
    );

    $container->set(
      'language_manager',
      $this->createLanguageManagerDouble()
    );

    $container->set(
      'uuid',
      $this->createUuidDouble()
    );

    $container->set(
      'module_handler',
      $this->createModuleHandlerDouble()
    );

    $container->set(
      'entity_field.manager',
      $this->createEntityFieldManagerDouble()
    );

    return $container;
  }

  /**
   * Creates an EntityTypeManager double.
   *
   * @param array<string, array{class: class-string, keys: array<string, string>}> $entityTypeConfigs
   *   Entity type configurations.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The EntityTypeManager double.
   */
  private function createEntityTypeManagerDouble(array $entityTypeConfigs): EntityTypeManagerInterface {
    $entityTypes = [];
    foreach ($entityTypeConfigs as $entityTypeId => $config) {
      $entityTypes[$entityTypeId] = $this->createEntityTypeDouble($entityTypeId, $config);
    }

    $mock = $this->createMock(EntityTypeManagerInterface::class);

    $mock->method('getDefinition')
      ->willReturnCallback(function (string $entityTypeId) use ($entityTypes) {
        if (!isset($entityTypes[$entityTypeId])) {
          throw new \InvalidArgumentException("Entity type '$entityTypeId' not registered.");
        }
        return $entityTypes[$entityTypeId];
      });

    $mock->method('getDefinitions')
      ->willReturn($entityTypes);

    $mock->method('hasDefinition')
      ->willReturnCallback(fn(string $entityTypeId) => isset($entityTypes[$entityTypeId]));

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $mock */
    return $mock;
  }

  /**
   * Creates an entity type definition double.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array{class: class-string, keys: array<string, string>} $config
   *   The entity type configuration.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface
   *   The entity type double.
   */
  private function createEntityTypeDouble(string $entityTypeId, array $config): ContentEntityTypeInterface {
    $mock = $this->createMock(ContentEntityTypeInterface::class);

    $mock->method('id')->willReturn($entityTypeId);
    $mock->method('getClass')->willReturn($config['class']);
    $mock->method('getKey')->willReturnCallback(
      fn(string $key) => $config['keys'][$key] ?? NULL
    );
    $mock->method('getKeys')->willReturn($config['keys']);
    $mock->method('hasKey')->willReturnCallback(
      fn(string $key) => isset($config['keys'][$key])
    );
    $mock->method('isRevisionable')->willReturn(isset($config['keys']['revision']));
    $mock->method('isTranslatable')->willReturn(isset($config['keys']['langcode']));
    $mock->method('getBundleEntityType')->willReturn(NULL);
    $mock->method('getLabel')->willReturn($entityTypeId);
    $mock->method('getLinkTemplates')->willReturn([]);
    $mock->method('getUriCallback')->willReturn(NULL);

    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $mock */
    return $mock;
  }

  /**
   * Creates an EntityTypeBundleInfo service double.
   *
   * @param array<string, array{class: class-string, keys: array<string, string>}> $entityTypeConfigs
   *   Entity type configurations.
   *
   * @return \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   *   The bundle info service double.
   */
  private function createBundleInfoDouble(array $entityTypeConfigs): EntityTypeBundleInfoInterface {
    $mock = $this->createMock(EntityTypeBundleInfoInterface::class);

    $mock->method('getBundleInfo')
      ->willReturnCallback(function (string $entityTypeId) use ($entityTypeConfigs) {
        if (!isset($entityTypeConfigs[$entityTypeId])) {
          return [];
        }
        // Return a minimal bundle info.
        return [
          $entityTypeId => [
            'label' => $entityTypeId,
          ],
        ];
      });

    $mock->method('getAllBundleInfo')
      ->willReturnCallback(function () use ($entityTypeConfigs) {
        $result = [];
        foreach (array_keys($entityTypeConfigs) as $entityTypeId) {
          $result[$entityTypeId] = [
            $entityTypeId => ['label' => $entityTypeId],
          ];
        }
        return $result;
      });

    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $mock */
    return $mock;
  }

  /**
   * Creates a LanguageManager double.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The LanguageManager double.
   */
  private function createLanguageManagerDouble(): LanguageManagerInterface {
    $defaultLanguage = new Language([
      'id' => LanguageInterface::LANGCODE_DEFAULT,
      'name' => 'Default',
    ]);

    $mock = $this->createMock(LanguageManagerInterface::class);

    $mock->method('getDefaultLanguage')->willReturn($defaultLanguage);
    $mock->method('getCurrentLanguage')->willReturn($defaultLanguage);
    $mock->method('getLanguage')
      ->willReturnCallback(function (?string $langcode) use ($defaultLanguage) {
        if ($langcode === NULL || $langcode === LanguageInterface::LANGCODE_DEFAULT) {
          return $defaultLanguage;
        }
        return new Language(['id' => $langcode, 'name' => $langcode]);
      });
    $mock->method('getLanguages')->willReturn([
      LanguageInterface::LANGCODE_DEFAULT => $defaultLanguage,
    ]);
    $mock->method('isMultilingual')->willReturn(FALSE);

    /** @var \Drupal\Core\Language\LanguageManagerInterface $mock */
    return $mock;
  }

  /**
   * Creates a UUID generator double.
   *
   * @return \Drupal\Component\Uuid\UuidInterface
   *   The UUID generator double.
   */
  private function createUuidDouble(): UuidInterface {
    $mock = $this->createMock(UuidInterface::class);

    $counter = 0;
    $mock->method('generate')
      ->willReturnCallback(function () use (&$counter) {
        $counter++;
        return sprintf(
          '%08x-%04x-%04x-%04x-%012x',
          $counter,
          0,
          0,
          0,
          0
        );
      });

    /** @var \Drupal\Component\Uuid\UuidInterface $mock */
    return $mock;
  }

  /**
   * Creates a ModuleHandler double.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The ModuleHandler double.
   */
  private function createModuleHandlerDouble(): ModuleHandlerInterface {
    $mock = $this->createMock(ModuleHandlerInterface::class);

    // Module handler is a no-op - hooks don't fire.
    $mock->method('invokeAll')->willReturn([]);
    $mock->method('invoke')->willReturn(NULL);
    $mock->method('moduleExists')->willReturn(FALSE);
    $mock->method('alter');

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $mock */
    return $mock;
  }

  /**
   * Creates an EntityFieldManager double.
   *
   * Returns empty field definitions so entities can be instantiated without
   * real field configuration.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The EntityFieldManager double.
   */
  private function createEntityFieldManagerDouble(): EntityFieldManagerInterface {
    $mock = $this->createMock(EntityFieldManagerInterface::class);

    // Return empty field definitions - fields are injected separately.
    $mock->method('getFieldDefinitions')->willReturn([]);
    $mock->method('getBaseFieldDefinitions')->willReturn([]);
    $mock->method('getFieldStorageDefinitions')->willReturn([]);
    $mock->method('getFieldMapByFieldType')->willReturn([]);

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $mock */
    return $mock;
  }

  /**
   * Creates a mock object using PHPUnit.
   *
   * @param class-string $interface
   *   The interface to mock.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   The mock object.
   */
  private function createMock(string $interface): object {
    $reflection = new \ReflectionMethod($this->testCase, 'createMock');
    /** @var \PHPUnit\Framework\MockObject\MockObject */
    return $reflection->invoke($this->testCase, $interface);
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldDefinitionMock(string $fieldName): FieldDefinitionInterface {
    $mock = $this->createMock(FieldDefinitionInterface::class);

    $mock->method('getName')->willReturn($fieldName);

    // Mock field storage definition for entity key access.
    $storageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storageDefinition->method('getMainPropertyName')->willReturn('value');

    $mock->method('getFieldStorageDefinition')->willReturn($storageDefinition);
    $mock->method('isTranslatable')->willReturn(FALSE);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $mock */
    return $mock;
  }

}
