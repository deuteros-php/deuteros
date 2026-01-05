<?php

declare(strict_types=1);

namespace Deuteros\Entity\PhpUnit;

use Deuteros\Entity\ServiceMockerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
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
 * Creates mock services using PHPUnit mocks.
 */
final class PhpUnitServiceMocker implements ServiceMockerInterface {

  /**
   * The test case.
   */
  private readonly TestCase $testCase;

  /**
   * Constructs a PhpUnitServiceMocker.
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
  public function buildContainer(array $entityTypeConfigs): ContainerInterface {
    $container = new ContainerBuilder();

    // Create and register mock services.
    $container->set(
      'entity_type.manager',
      $this->createEntityTypeManagerMock($entityTypeConfigs)
    );

    $container->set(
      'entity_type.bundle.info',
      $this->createBundleInfoMock($entityTypeConfigs)
    );

    $container->set(
      'language_manager',
      $this->createLanguageManagerMock()
    );

    $container->set(
      'uuid',
      $this->createUuidMock()
    );

    $container->set(
      'module_handler',
      $this->createModuleHandlerMock()
    );

    $container->set(
      'entity_field.manager',
      $this->createEntityFieldManagerMock()
    );

    return $container;
  }

  /**
   * Creates a mock EntityTypeManager.
   *
   * @param array<string, array{class: class-string, keys: array<string, string>}> $entityTypeConfigs
   *   Entity type configurations.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The mock EntityTypeManager.
   */
  private function createEntityTypeManagerMock(array $entityTypeConfigs): EntityTypeManagerInterface {
    $entityTypes = [];
    foreach ($entityTypeConfigs as $entityTypeId => $config) {
      $entityTypes[$entityTypeId] = $this->createEntityTypeMock($entityTypeId, $config);
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
   * Creates a mock entity type definition.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array{class: class-string, keys: array<string, string>} $config
   *   The entity type configuration.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface
   *   The mock entity type.
   */
  private function createEntityTypeMock(string $entityTypeId, array $config): ContentEntityTypeInterface {
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
   * Creates a mock EntityTypeBundleInfo service.
   *
   * @param array<string, array{class: class-string, keys: array<string, string>}> $entityTypeConfigs
   *   Entity type configurations.
   *
   * @return \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   *   The mock bundle info service.
   */
  private function createBundleInfoMock(array $entityTypeConfigs): EntityTypeBundleInfoInterface {
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
   * Creates a mock LanguageManager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The mock LanguageManager.
   */
  private function createLanguageManagerMock(): LanguageManagerInterface {
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
   * Creates a mock UUID generator.
   *
   * @return \Drupal\Component\Uuid\UuidInterface
   *   The mock UUID generator.
   */
  private function createUuidMock(): UuidInterface {
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
   * Creates a mock ModuleHandler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The mock ModuleHandler.
   */
  private function createModuleHandlerMock(): ModuleHandlerInterface {
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
   * Creates a mock EntityFieldManager.
   *
   * Returns empty field definitions so entities can be instantiated without
   * real field configuration.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The mock EntityFieldManager.
   */
  private function createEntityFieldManagerMock(): EntityFieldManagerInterface {
    $mock = $this->createMock(EntityFieldManagerInterface::class);

    // Return empty field definitions - fields are injected separately.
    $mock->method('getFieldDefinitions')->willReturn([]);
    $mock->method('getBaseFieldDefinitions')->willReturn([]);
    $mock->method('getFieldStorageDefinitions')->willReturn([]);

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

}
