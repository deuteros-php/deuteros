<?php

declare(strict_types=1);

namespace Deuteros\Entity\Prophecy;

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
use Prophecy\Argument;
use Prophecy\Prophet;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates service doubles using Prophecy.
 */
final class ProphecyServiceDoubler implements ServiceDoublerInterface {

  /**
   * The prophet for creating prophecies.
   */
  private readonly Prophet $prophet;

  /**
   * Constructs a ProphecyServiceDoubler.
   *
   * @param \PHPUnit\Framework\TestCase $testCase
   *   The test case (must use ProphecyTrait).
   */
  public function __construct(TestCase $testCase) {
    if (!method_exists($testCase, 'getProphet')) {
      throw new \InvalidArgumentException(
        'Test case must use ProphecyTrait to use ProphecyServiceDoubler.'
      );
    }
    // Use reflection to call getProphet() since it may be private/protected.
    $reflection = new \ReflectionMethod($testCase, 'getProphet');
    /** @var \Prophecy\Prophet $prophet */
    $prophet = $reflection->invoke($testCase);
    $this->prophet = $prophet;
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

    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\EntityTypeManagerInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(EntityTypeManagerInterface::class);

    $prophecy->getDefinition(Argument::type('string'))
      ->will(function (array $args) use ($entityTypes) {
        $entityTypeId = $args[0];
        assert(is_string($entityTypeId));
        if (!isset($entityTypes[$entityTypeId])) {
          throw new \InvalidArgumentException("Entity type '$entityTypeId' not registered.");
        }
        return $entityTypes[$entityTypeId];
      });

    $prophecy->getDefinitions()->willReturn($entityTypes);

    $prophecy->hasDefinition(Argument::type('string'))
      ->will(function (array $args) use ($entityTypes) {
        $entityTypeId = $args[0];
        assert(is_string($entityTypeId));
        return isset($entityTypes[$entityTypeId]);
      });

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
    return $prophecy->reveal();
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
    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\ContentEntityTypeInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(ContentEntityTypeInterface::class);

    $prophecy->id()->willReturn($entityTypeId);
    $prophecy->getClass()->willReturn($config['class']);
    $prophecy->getKey(Argument::type('string'))
      ->will(function (array $args) use ($config) {
        $key = $args[0];
        assert(is_string($key));
        return $config['keys'][$key] ?? NULL;
      });
    $prophecy->getKeys()->willReturn($config['keys']);
    $prophecy->hasKey(Argument::type('string'))
      ->will(function (array $args) use ($config) {
        $key = $args[0];
        assert(is_string($key));
        return isset($config['keys'][$key]);
      });
    $prophecy->isRevisionable()->willReturn(isset($config['keys']['revision']));
    $prophecy->isTranslatable()->willReturn(isset($config['keys']['langcode']));
    $prophecy->getBundleEntityType()->willReturn(NULL);
    $prophecy->getLabel()->willReturn($entityTypeId);
    $prophecy->getLinkTemplates()->willReturn([]);
    $prophecy->getUriCallback()->willReturn(NULL);

    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface */
    return $prophecy->reveal();
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
    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\EntityTypeBundleInfoInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(EntityTypeBundleInfoInterface::class);

    $prophecy->getBundleInfo(Argument::type('string'))
      ->will(function (array $args) use ($entityTypeConfigs) {
        $entityTypeId = $args[0];
        assert(is_string($entityTypeId));
        if (!isset($entityTypeConfigs[$entityTypeId])) {
          return [];
        }
        return [
          $entityTypeId => ['label' => $entityTypeId],
        ];
      });

    $prophecy->getAllBundleInfo()
      ->will(function () use ($entityTypeConfigs) {
        $result = [];
        foreach (array_keys($entityTypeConfigs) as $entityTypeId) {
          $result[$entityTypeId] = [
            $entityTypeId => ['label' => $entityTypeId],
          ];
        }
        return $result;
      });

    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface */
    return $prophecy->reveal();
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

    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Language\LanguageManagerInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(LanguageManagerInterface::class);

    $prophecy->getDefaultLanguage()->willReturn($defaultLanguage);
    $prophecy->getCurrentLanguage(Argument::any())->willReturn($defaultLanguage);
    $prophecy->getLanguage(Argument::any())
      ->will(function (array $args) use ($defaultLanguage) {
        $langcode = $args[0];
        if ($langcode === NULL || $langcode === LanguageInterface::LANGCODE_DEFAULT) {
          return $defaultLanguage;
        }
        assert(is_string($langcode));
        return new Language(['id' => $langcode, 'name' => $langcode]);
      });
    $prophecy->getLanguages(Argument::any())->willReturn([
      LanguageInterface::LANGCODE_DEFAULT => $defaultLanguage,
    ]);
    $prophecy->isMultilingual()->willReturn(FALSE);

    /** @var \Drupal\Core\Language\LanguageManagerInterface */
    return $prophecy->reveal();
  }

  /**
   * Creates a UUID generator double.
   *
   * @return \Drupal\Component\Uuid\UuidInterface
   *   The UUID generator double.
   */
  private function createUuidDouble(): UuidInterface {
    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Component\Uuid\UuidInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(UuidInterface::class);

    $prophecy->generate()->will(function (): string {
      /** @var int $counter */
      static $counter = 0;
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

    /** @var \Drupal\Component\Uuid\UuidInterface */
    return $prophecy->reveal();
  }

  /**
   * Creates a ModuleHandler double.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The ModuleHandler double.
   */
  private function createModuleHandlerDouble(): ModuleHandlerInterface {
    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Extension\ModuleHandlerInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(ModuleHandlerInterface::class);

    $prophecy->invokeAll(Argument::any(), Argument::any())->willReturn([]);
    $prophecy->invoke(Argument::any(), Argument::any(), Argument::any())->willReturn(NULL);
    $prophecy->moduleExists(Argument::any())->willReturn(FALSE);
    $prophecy->alter(Argument::cetera());

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface */
    return $prophecy->reveal();
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
    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\EntityFieldManagerInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(EntityFieldManagerInterface::class);

    $prophecy->getFieldDefinitions(Argument::any(), Argument::any())->willReturn([]);
    $prophecy->getBaseFieldDefinitions(Argument::any())->willReturn([]);
    $prophecy->getFieldStorageDefinitions(Argument::any())->willReturn([]);
    $prophecy->getFieldMapByFieldType(Argument::any())->willReturn([]);

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface */
    return $prophecy->reveal();
  }

  /**
   * {@inheritdoc}
   */
  public function createFieldDefinitionMock(string $fieldName): FieldDefinitionInterface {
    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Field\FieldDefinitionInterface> $prophecy */
    $prophecy = $this->prophet->prophesize(FieldDefinitionInterface::class);

    $prophecy->getName()->willReturn($fieldName);

    // Mock field storage definition for entity key access.
    /** @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Field\FieldStorageDefinitionInterface> $storageProphecy */
    $storageProphecy = $this->prophet->prophesize(FieldStorageDefinitionInterface::class);
    $storageProphecy->getMainPropertyName()->willReturn('value');

    $prophecy->getFieldStorageDefinition()->willReturn($storageProphecy->reveal());
    $prophecy->isTranslatable()->willReturn(FALSE);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface */
    return $prophecy->reveal();
  }

}
