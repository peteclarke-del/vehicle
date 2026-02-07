<?php

namespace App\Controller\Trait;

/**
 * Provides common entity hydration methods for updating entities from request data
 */
trait EntityHydrationTrait
{
    /**
     * Set a date field on an entity from request data
     *
     * @param object $entity
     * @param array $data Request data
     * @param string $key Key in the data array
     * @param string $setter Setter method name on the entity
     * @return void
     */
    protected function setDateFromData(object $entity, array $data, string $key, string $setter): void
    {
        if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
            $entity->$setter(new \DateTime($data[$key]));
        }
    }

    /**
     * Set a nullable date field on an entity from request data
     * Handles empty/null values by setting null on the entity
     *
     * @param object $entity
     * @param array $data Request data
     * @param string $key Key in the data array
     * @param string $setter Setter method name on the entity
     * @return void
     */
    protected function setNullableDateFromData(object $entity, array $data, string $key, string $setter): void
    {
        if (array_key_exists($key, $data)) {
            $value = $data[$key];
            if ($value === null || $value === '') {
                $entity->$setter(null);
            } else {
                $entity->$setter(new \DateTime($value));
            }
        }
    }

    /**
     * Set a string field on an entity from request data
     *
     * @param object $entity
     * @param array $data Request data
     * @param string $key Key in the data array
     * @param string $setter Setter method name on the entity
     * @param string|null $default Default value if not set
     * @return void
     */
    protected function setStringFromData(object $entity, array $data, string $key, string $setter, ?string $default = null): void
    {
        if (isset($data[$key])) {
            $entity->$setter($data[$key]);
        } elseif ($default !== null) {
            $entity->$setter($default);
        }
    }

    /**
     * Set a nullable string field on an entity from request data
     *
     * @param object $entity
     * @param array $data Request data
     * @param string $key Key in the data array
     * @param string $setter Setter method name on the entity
     * @return void
     */
    protected function setNullableStringFromData(object $entity, array $data, string $key, string $setter): void
    {
        if (array_key_exists($key, $data)) {
            $value = $data[$key];
            $entity->$setter($value === '' ? null : $value);
        }
    }

    /**
     * Set a numeric field on an entity from request data
     *
     * @param object $entity
     * @param array $data Request data
     * @param string $key Key in the data array
     * @param string $setter Setter method name on the entity
     * @param bool $nullable Whether to allow null values
     * @return void
     */
    protected function setNumericFromData(object $entity, array $data, string $key, string $setter, bool $nullable = true): void
    {
        if (array_key_exists($key, $data)) {
            $value = $data[$key];
            if ($nullable && ($value === null || $value === '')) {
                $entity->$setter(null);
            } else {
                $entity->$setter(is_numeric($value) ? (float) $value : null);
            }
        }
    }

    /**
     * Set a boolean field on an entity from request data
     *
     * @param object $entity
     * @param array $data Request data
     * @param string $key Key in the data array
     * @param string $setter Setter method name on the entity
     * @param bool $default Default value if not set
     * @return void
     */
    protected function setBooleanFromData(object $entity, array $data, string $key, string $setter, bool $default = false): void
    {
        if (array_key_exists($key, $data)) {
            $entity->$setter((bool) $data[$key]);
        } else {
            $entity->$setter($default);
        }
    }

    /**
     * Set an integer field on an entity from request data
     *
     * @param object $entity
     * @param array $data Request data
     * @param string $key Key in the data array
     * @param string $setter Setter method name on the entity
     * @param bool $nullable Whether to allow null values
     * @return void
     */
    protected function setIntegerFromData(object $entity, array $data, string $key, string $setter, bool $nullable = true): void
    {
        if (array_key_exists($key, $data)) {
            $value = $data[$key];
            if ($nullable && ($value === null || $value === '')) {
                $entity->$setter(null);
            } else {
                $entity->$setter((int) $value);
            }
        }
    }
}
