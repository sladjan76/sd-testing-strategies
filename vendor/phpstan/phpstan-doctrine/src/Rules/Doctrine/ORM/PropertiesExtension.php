<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Throwable;
use function in_array;

class PropertiesExtension implements ReadWritePropertiesExtension
{

	private ObjectMetadataResolver $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
	{
		$className = $property->getDeclaringClass()->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
			return false;
		}

		return $metadata->hasField($propertyName) || $metadata->hasAssociation($propertyName);
	}

	public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
			return false;
		}

		if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
			return false;
		}

		if (isset($metadata->fieldMappings[$propertyName])) {
			$mapping = $metadata->fieldMappings[$propertyName];
			if (isset($mapping['generated']) && $mapping['generated'] !== ClassMetadata::GENERATED_NEVER) {
				return true;
			}
		}

		if ($metadata->isReadOnly && !$declaringClass->hasConstructor()) {
			return true;
		}

		if ($metadata->versionField === $propertyName) {
			return true;
		}

		return $this->isGeneratedIdentifier($metadata, $propertyName);
	}

	public function isInitialized(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
			return false;
		}

		if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
			return false;
		}

		if ($this->isGeneratedIdentifier($metadata, $propertyName)) {
			return true;
		}

		return $metadata->isReadOnly && !$declaringClass->hasConstructor();
	}

	/**
	 * @param ClassMetadata<object> $metadata
	 */
	private function isGeneratedIdentifier(ClassMetadata $metadata, string $propertyName): bool
	{
		if ($metadata->isIdentifierNatural()) {
			return false;
		}

		try {
			return in_array($propertyName, $metadata->getIdentifierFieldNames(), true);
		} catch (Throwable $e) {
			$mappingException = 'Doctrine\ORM\Mapping\MappingException';
			if (!$e instanceof $mappingException) {
				throw $e;
			}

			return false;
		}
	}

}
