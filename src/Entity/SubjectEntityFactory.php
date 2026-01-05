<?php

declare(strict_types=1);

namespace Deuteros\Entity;

use Deuteros\Common\EntityDoubleDefinitionBuilder;
use Deuteros\Common\EntityDoubleFactory;
use Deuteros\Common\EntityDoubleFactoryInterface;
use Deuteros\Entity\PhpUnit\PhpUnitServiceDoubler;
use Deuteros\Entity\Prophecy\ProphecyServiceDoubler;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Factory for creating subject entity instances in unit tests.
 *
 * This factory creates real Drupal entity instances (Node, User, etc.) with
 * doubled service dependencies and DEUTEROS field doubles injected directly
 * into the entity's field cache.
 *
 * Unlike "EntityDoubleFactory" which creates double implementations of entity
 * interfaces, this factory instantiates actual entity classes with their
 * service dependencies doubled and field values provided as DEUTEROS doubles.
 *
 * The term "subject" refers to the entity class being tested, as opposed to
 * "doubles" which are test substitutes for dependencies.
 *
 * @example Basic usage
 * ```php
 * class MyNodeTest extends TestCase {
 *     private SubjectEntityFactory $factory;
 *
 *     protected function setUp(): void {
 *         $this->factory = SubjectEntityFactory::fromTest($this);
 *         $this->factory->installContainer();
 *     }
 *
 *     protected function tearDown(): void {
 *         $this->factory->uninstallContainer();
 *     }
 *
 *     public function testNodeCreation(): void {
 *         $node = $this->factory->create(Node::class, [
 *             'nid' => 1,
 *             'type' => 'article',
 *             'title' => 'Test Article',
 *         ]);
 *
 *         $this->assertInstanceOf(Node::class, $node);
 *         $this->assertEquals('Test Article', $node->get('title')->value);
 *     }
 * }
 * ```
 */
final class SubjectEntityFactory {

  /**
   * Cache of extracted entity type configurations keyed by class name.
   *
   * @var array<class-string, array{id: string, keys: array<string, string>}>
   */
  private static array $entityTypeConfigCache = [];

  /**
   * The original container (for restoration).
   */
  private ?ContainerInterface $originalContainer = NULL;

  /**
   * Whether the container has been installed.
   */
  private bool $containerInstalled = FALSE;

  /**
   * Entity type configurations registered via ::installContainer.
   *
   * @var array<string, array{class: class-string, keys: array<string, string>}>
   */
  private array $entityTypeConfigs = [];

  /**
   * Creates a SubjectEntityFactory from a test case.
   *
   * Auto-detects whether the test uses Prophecy (via "ProphecyTrait") or
   * PHPUnit mocks and returns a factory configured with the appropriate
   * service doubler.
   *
   * @param \PHPUnit\Framework\TestCase $test
   *   The test case instance.
   *
   * @return self
   *   A new factory instance.
   */
  public static function fromTest(TestCase $test): self {
    $usesProphecy = method_exists($test, 'getProphet');

    $serviceDoubler = $usesProphecy
      ? new ProphecyServiceDoubler($test)
      : new PhpUnitServiceDoubler($test);

    $doubleFactory = EntityDoubleFactory::fromTest($test);

    return new self($serviceDoubler, $doubleFactory);
  }

  /**
   * Constructs a SubjectEntityFactory.
   *
   * @param \Deuteros\Entity\ServiceDoublerInterface $serviceDoubler
   *   The service doubler.
   * @param \Deuteros\Common\EntityDoubleFactoryInterface $doubleFactory
   *   The entity double factory for creating field doubles.
   */
  private function __construct(
    private readonly ServiceDoublerInterface $serviceDoubler,
    private readonly EntityDoubleFactoryInterface $doubleFactory,
  ) {
  }

  /**
   * Installs the doubled container for entity testing.
   *
   * Sets up a Symfony container with doubled services required for entity
   * instantiation and sets it on "\Drupal::setContainer()".
   *
   * Call this method in your test's "setUp()" method.
   */
  public function installContainer(): void {
    if ($this->containerInstalled) {
      throw new \LogicException(
        'Container already installed. Call uninstallContainer() first.'
      );
    }

    // Save original container for restoration.
    // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
    if (\Drupal::hasContainer()) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $this->originalContainer = \Drupal::getContainer();
    }

    // Build and install the container with doubled services.
    $container = $this->serviceDoubler->buildContainer($this->entityTypeConfigs);
    // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
    \Drupal::setContainer($container);

    $this->containerInstalled = TRUE;
  }

  /**
   * Uninstalls the doubled container.
   *
   * Restores the original container (if any) or unsets the container.
   * Call this method in your test's "tearDown()" method.
   */
  public function uninstallContainer(): void {
    if (!$this->containerInstalled) {
      return;
    }

    if ($this->originalContainer !== NULL) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      \Drupal::setContainer($this->originalContainer);
      $this->originalContainer = NULL;
    }
    else {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      \Drupal::unsetContainer();
    }

    $this->containerInstalled = FALSE;
    $this->entityTypeConfigs = [];
  }

  /**
   * Creates a subject entity instance with DEUTEROS field doubles.
   *
   * Instantiates the specified entity class, creates field doubles from the
   * provided values, and injects them into the entity's field cache.
   *
   * @param class-string $entityClass
   *   The entity class to instantiate (e.g., Node::class).
   * @param array<string, mixed> $values
   *   Field values. Entity keys (id, bundle, etc.) are used for the entity
   *   constructor. Other values are converted to field doubles.
   *
   * @return \Drupal\Core\Entity\ContentEntityBase
   *   The created entity instance.
   *
   * @throws \InvalidArgumentException
   *   If the entity class is not a ContentEntityBase subclass.
   * @throws \LogicException
   *   If ::installContainer has not been called.
   */
  public function create(string $entityClass, array $values = []): ContentEntityBase {
    if (!$this->containerInstalled) {
      throw new \LogicException(
        'Container not installed. Call installContainer() before create().'
      );
    }

    if (!is_subclass_of($entityClass, ContentEntityBase::class)) {
      throw new \InvalidArgumentException(sprintf(
        'Entity class %s must be a subclass of %s.',
        $entityClass,
        ContentEntityBase::class
      ));
    }

    /** @var class-string<\Drupal\Core\Entity\ContentEntityBase> $entityClass */

    // Extract entity type configuration from class attributes.
    $config = $this->getEntityTypeConfig($entityClass);

    // Register the entity type configuration if not already registered.
    if (!isset($this->entityTypeConfigs[$config['id']])) {
      $this->entityTypeConfigs[$config['id']] = [
        'class' => $entityClass,
        'keys' => $config['keys'],
      ];
      // Rebuild container with new entity type.
      $container = $this->serviceDoubler->buildContainer($this->entityTypeConfigs);
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      \Drupal::setContainer($container);
    }

    // Create entity instance without calling the constructor.
    // This bypasses all the service dependencies in ContentEntityBase.
    $reflection = new \ReflectionClass($entityClass);
    $entity = $reflection->newInstanceWithoutConstructor();

    // Set up required internal properties via reflection.
    $this->initializeEntityProperties($entity, $values, $config);

    // Create and inject field doubles.
    $fieldDoubles = $this->createFieldDoubles($values, $config);
    $this->injectFieldDoubles($entity, $fieldDoubles);

    return $entity;
  }

  /**
   * Initializes entity properties via reflection.
   *
   * Sets the minimal required properties for an entity to function without
   * calling the full constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The entity instance.
   * @param array<string, mixed> $values
   *   The field values.
   * @param array{id: string, keys: array<string, string>} $config
   *   The entity type configuration.
   */
  private function initializeEntityProperties(ContentEntityBase $entity, array $values, array $config): void {
    $baseReflection = new \ReflectionClass(ContentEntityBase::class);

    // Set entityTypeId.
    $entityTypeIdProperty = $baseReflection->getProperty('entityTypeId');
    $entityTypeIdProperty->setValue($entity, $config['id']);

    // Set bundle via entityKeys.
    $bundleKey = $config['keys']['bundle'] ?? 'type';
    $bundleValue = $values[$bundleKey] ?? $config['id'];
    $entityKeysProperty = $baseReflection->getProperty('entityKeys');
    $entityKeys = ['bundle' => $bundleValue];

    // Set id if provided.
    if (isset($config['keys']['id']) && isset($values[$config['keys']['id']])) {
      $entityKeys['id'] = $values[$config['keys']['id']];
    }

    // Set uuid if provided.
    if (isset($config['keys']['uuid']) && isset($values[$config['keys']['uuid']])) {
      $entityKeys['uuid'] = $values[$config['keys']['uuid']];
    }

    $entityKeysProperty->setValue($entity, $entityKeys);

    // Initialize empty arrays for internal state.
    $valuesProperty = $baseReflection->getProperty('values');
    $valuesProperty->setValue($entity, []);

    $fieldsProperty = $baseReflection->getProperty('fields');
    $fieldsProperty->setValue($entity, []);

    $translationsProperty = $baseReflection->getProperty('translations');
    $translationsProperty->setValue($entity, [
      LanguageInterface::LANGCODE_DEFAULT => ['status' => TRUE],
    ]);

    // Set default langcode.
    $defaultLangcodeProperty = $baseReflection->getProperty('defaultLangcode');
    $defaultLangcodeProperty->setValue($entity, LanguageInterface::LANGCODE_DEFAULT);

    // Initialize new revision flag.
    $newRevisionProperty = $baseReflection->getProperty('newRevision');
    $newRevisionProperty->setValue($entity, FALSE);

    // Initialize enforceIsNew.
    $enforceIsNewProperty = $baseReflection->getProperty('enforceIsNew');
    $enforceIsNewProperty->setValue($entity, NULL);

    // Initialize languages.
    $languagesProperty = $baseReflection->getProperty('languages');
    $languagesProperty->setValue($entity, []);
  }

  /**
   * Gets the entity double factory.
   *
   * Useful for creating entity doubles to use as entity references.
   *
   * @return \Deuteros\Common\EntityDoubleFactoryInterface
   *   The entity double factory.
   */
  public function getDoubleFactory(): EntityDoubleFactoryInterface {
    return $this->doubleFactory;
  }

  /**
   * Extracts entity type configuration from class attributes.
   *
   * @param class-string $entityClass
   *   The entity class.
   *
   * @return array{id: string, keys: array<string, string>}
   *   The entity type configuration.
   *
   * @throws \InvalidArgumentException
   *   If the entity class doesn't have a ContentEntityType attribute.
   */
  private function getEntityTypeConfig(string $entityClass): array {
    if (isset(self::$entityTypeConfigCache[$entityClass])) {
      return self::$entityTypeConfigCache[$entityClass];
    }

    $reflection = new \ReflectionClass($entityClass);

    // Check for PHP 8 ContentEntityType attribute.
    $attributes = $reflection->getAttributes(ContentEntityType::class);

    if (empty($attributes)) {
      throw new \InvalidArgumentException(sprintf(
        'Entity class %s does not have a #[ContentEntityType] attribute.',
        $entityClass
      ));
    }

    $attribute = $attributes[0]->newInstance();

    // Extract entity_keys via reflection (it's protected).
    $attributeReflection = new \ReflectionClass($attribute);
    $entityKeysProperty = $attributeReflection->getProperty('entity_keys');
    /** @var array<string, string> $entityKeys */
    $entityKeys = $entityKeysProperty->getValue($attribute);

    $config = [
      'id' => $attribute->id,
      'keys' => $entityKeys,
    ];

    self::$entityTypeConfigCache[$entityClass] = $config;

    return $config;
  }

  /**
   * Creates field doubles from values.
   *
   * @param array<string, mixed> $values
   *   The raw values.
   * @param array{id: string, keys: array<string, string>} $config
   *   The entity type configuration.
   *
   * @return array<string, \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
   *   Field doubles keyed by field name.
   */
  private function createFieldDoubles(array $values, array $config): array {
    /** @var array<string, \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>> $fields */
    $fields = [];

    foreach ($values as $fieldName => $value) {
      // Create a minimal entity definition for field double creation.
      // FieldableEntityInterface is auto-added when fields are defined.
      $builder = EntityDoubleDefinitionBuilder::create($config['id']);
      $builder->field($fieldName, $value);
      $definition = $builder->build();

      // Create the entity double - we'll extract just the field from it.
      $tempEntity = $this->doubleFactory->create($definition);

      // Get the field double (FieldableEntityInterface was auto-added).
      assert($tempEntity instanceof FieldableEntityInterface);
      $fields[$fieldName] = $tempEntity->get($fieldName);
    }

    return $fields;
  }

  /**
   * Injects field doubles into the entity's field cache.
   *
   * Uses reflection to populate "$this->fields" directly, bypassing
   * the normal field instantiation which requires service dependencies.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The entity instance.
   * @param array<string, \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>> $fields
   *   Field doubles keyed by field name.
   */
  private function injectFieldDoubles(ContentEntityBase $entity, array $fields): void {
    $reflection = new \ReflectionClass(ContentEntityBase::class);
    $fieldsProperty = $reflection->getProperty('fields');

    $langcode = LanguageInterface::LANGCODE_DEFAULT;
    $fieldsArray = [];

    foreach ($fields as $fieldName => $fieldDouble) {
      $fieldsArray[$fieldName][$langcode] = $fieldDouble;
    }

    $fieldsProperty->setValue($entity, $fieldsArray);
  }

}
