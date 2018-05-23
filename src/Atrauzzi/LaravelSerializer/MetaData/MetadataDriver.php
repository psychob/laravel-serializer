<?php

namespace Atrauzzi\LaravelSerializer\MetaData;

use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use JMS\Serializer\Metadata\VirtualPropertyMetadata;
use Metadata\Driver\AdvancedDriverInterface;
//
use Illuminate\Config\Repository;
//
use JMS\Serializer\Metadata\ClassMetadata;
use ReflectionClass;

/**
 * Class MetadataDriver
 *
 * This metadata driver integrates JMS Serializer with the Laravel Framework
 *
 * Mappings are maintained as Laravel configuration files and are read on demand.  Conventions mimic the
 * mutator system already present in Eloquent so that the language remains consistent for the majority of cases.
 *
 * @package Atrauzzi\LaravelSerializer
 */
class MetadataDriver implements AdvancedDriverInterface
{

    /** @var \Illuminate\Config\Repository */
    protected $config;

    public function __construct(
        Repository $config
    ) {
        $this->config = $config;
    }

    /**
     * Gets all the metadata class names known to this driver.
     *
     * @return array
     */
    public function getAllClassNames()
    {
        return array_keys($this->config->get('serializer.mappings'));
    }

    /**
     * When serializer wants to serialize a class, it will ask this method to produce the metadata.
     *
     * @param \ReflectionClass $class
     * @return \Metadata\ClassMetadata
     */
    public function loadMetadataForClass(ReflectionClass $class)
    {
        $className = $class->name;
        $classMetadata = new ClassData($className);
        $mappingConfig = $this->config->get(sprintf('serializer.mappings.%s', $className));
        $prependType = $this->config->get('serializer.prepend_type', false);

        // If the class is an instance of Model, as a convenience, pre-configure $visible as defaults.
        $mappingConfig = $this->assignPublicModelFields($class, $mappingConfig);

        // Generate a Type Meta-Property
        $this->prependType($class,
            $this->config->get(sprintf('serializer.mappings.%s.meta_property', $className), null) ?? $prependType,
            $classMetadata, $className);

        // Generate object hierarchy
        $hierarchy = $this->generateObjectHierarchyFor($class);
        $properties = [];

        foreach ($hierarchy as $partialClassName) {
            $rClass = new ReflectionClass($partialClassName);

            $classMetadata->addFileResource($rClass->getFileName());

            $partialDefaultVisibility = $this->getDefaultVisibilityFor($partialClassName);
            $properties[$partialClassName] = $this->fetchClassAttributes($partialClassName, $partialDefaultVisibility, $rClass);
        }

        $calculatedProperties = $this->calculateCorrectProperties($properties);
        return $this->renderProperties($calculatedProperties, $class, $classMetadata);
    }

    /**
     * @param ReflectionClass $class
     * @param $mappingConfig
     * @return mixed
     */
    private function assignPublicModelFields(ReflectionClass $class, $mappingConfig)
    {
        if ($class->isSubclassOf('Illuminate\Database\Eloquent\Model')) {

            $defaultProperties = $class->getDefaultProperties();

            if (!empty($defaultProperties['visible'])) {
                $mappingConfig['attributes'] = array_merge($defaultProperties['visible'],
                    $mappingConfig['attributes']);
            }

        }

        return $mappingConfig;
    }

    /**
     * @param ReflectionClass $class
     * @param $prependType
     * @param $classMetadata
     * @param $className
     */
    private function prependType(
        ReflectionClass $class,
        bool $prependType,
        ClassMetadata $classMetadata,
        string $className
    ): void {
        if ($prependType) {
            $classMetadata->addPropertyMetadata(new StaticPropertyMetadata(
                $className,
                '_type',
                snake_case($class->getShortName())
            ));
        }
    }

    /**
     * Generate Object Hierarchy for $class
     *
     * @param ReflectionClass $class
     *
     * @return array
     */
    private function generateObjectHierarchyFor(ReflectionClass $class, bool $unique = true): array
    {
        $ret = [$class->getName()];

        $ret = array_merge($ret, $class->getInterfaceNames());

        if ($class->getParentClass()) {
            $ret = array_merge($ret, $this->generateObjectHierarchyFor($class->getParentClass(), false));
        }

        if ($unique) {
            $ret = array_unique($ret);
        }

        return $ret;
    }

    private function getDefaultVisibilityFor(string $className): array
    {
        $value = $this->config->get(sprintf('serializer.mappings.%s.meta_property', $className), []);

        if (is_string($value)) {
            $value = [$value];
        }

        return $value;
    }

    private function fetchClassAttributes(string $className, array $visibility, ReflectionClass $rClass): array
    {
        $properties = $this->fetchClassDefinedAttributes($className, $rClass);
        $properties = array_merge($properties, $this->fetchClassVisibilityAttributes($className, $rClass, $properties, $visibility));

        return $properties;
    }

    private function fetchClassDefinedAttributes(string $className, ReflectionClass $class): array
    {
        $defined = $this->config->get(sprintf('serializer.mappings.%s.attributes', $className), null);

        if ($defined === null) {
            return [];
        }

        $ret = [];

        foreach ($defined as $key => $value) {
            $line = [
                'type' => null,
                'name' => null,
                'internal_name' => null,
                'groups' => [],
                'accessor' => null,
                'attribute' => true,
            ];

            if (is_array($value)) {
                $line['name'] = snake_case($key);
                $line['internal_name'] = $key;

                if (!empty($value['name'])) {
                    $line['name'] = $value['name'];
                }

                if (!empty($value['groups'])) {
                    $line['groups'] = $value['groups'];
                }

                if (!empty($value['type'])) {
                    $line['type'] = $value['type'];
                }
            } elseif (is_string($key) && is_string($value)) {
                $line['name'] = snake_case($key);
                $line['internal_name'] = $key;
                $line['type'] = $value;
            } else {
                $line['internal_name'] = $value;
                $line['name'] = snake_case($value);
            }

            if ($class->hasProperty($line['internal_name'])) {
                $prop = $class->getProperty($line['internal_name']);

                if (!$prop->isPublic()) {
                    $line['accessor'] = 'get'.studly_case($line['internal_name']);
                }
            } else {
                $line['accessor'] = 'get'.studly_case($line['internal_name']);
            }

            $ret[] = $line;
        }

        return $ret;
    }

    private function fetchClassVisibilityAttributes(string $className, ReflectionClass $class, array $properties, array $visibility): array
    {
        // TODO: fetch properties based on visibility
        return [];
    }

    private function calculateCorrectProperties(array $properties): array
    {
        $ret = [];

        foreach ($properties as $class => $props) {
            foreach ($props as $property) {
                if (!empty($ret[$property['internal_name']])) {
                    if (!$ret[$property['internal_name']]['attribute']) {
                        $ret[$property['internal_name']] = $property;
                    }
                } else {
                    $ret[$property['internal_name']] = $property;
                }
            }
        }

        return $ret;
    }

    private function renderProperties(array $properties, ReflectionClass $class, ClassData $metadata): ClassData
    {
        foreach ($properties as $property) {
            if ($property['accessor']) {
                $prop = new VirtualPropertyMetadata($class->getName(), $property['accessor']);
                $prop->serializedName = $property['name'];
                $prop->groups = $property['groups'];
                if ($property['type']) {
                    $prop->setType($property['type']);
                }
            } else {
                $prop = new PropertyMetadata($class->getName(), $property['accessor']);
                $prop->serializedName = $property['name'];
                $prop->groups = $property['groups'];
                if ($property['type']) {
                    $prop->setType($property['type']);
                }
            }

            $metadata->addPropertyMetadata($prop);
        }

        return $metadata;
    }
}