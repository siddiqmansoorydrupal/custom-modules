<?php
namespace Drupal\stripe_pay\Plugin\Field\FieldType;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\Annotation\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
* Plugin implementation of the 'stripe_payment' field type.
*
* @FieldType(
*   id = "stripe_payment",
*   label = @Translation("Stripe Payment"),
 *  category = @Translation("Stripe Payment"),
*   default_widget = "stripe_payment_default_widget",
*   default_formatter = "stripe_payment_default_formatter"
* )
*/
class StripePaymentFieldItem extends FieldItemBase implements FieldItemInterface {

    /**
     * Defines field item properties.
     *
     * Properties that are required to constitute a valid, non-empty item should
     * be denoted with \Drupal\Core\TypedData\DataDefinition::setRequired().
     *
     * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
     *   An array of property definitions of contained properties, keyed by
     *   property name.
     *
     * @see \Drupal\Core\Field\BaseFieldDefinition
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties['stripe_payment'] = DataDefinition::create('string')
        ->setLabel(t('Stripe Payment'));
        return $properties;
    }

    /**
     * Returns the schema for the field.
     *
     * This method is static because the field schema information is needed on
     * creation of the field. FieldItemInterface objects instantiated at that
     * time are not reliable as field settings might be missing.
     *
     * Computed fields having no schema should return an empty array.
     *
     * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
     *   The field definition.
     *
     * @return array
     *   An empty array if there is no schema, or an associative array with the
     *   following key/value pairs:
     *   - columns: An array of Schema API column specifications, keyed by column
     *     name. The columns need to be a subset of the properties defined in
     *     propertyDefinitions(). The 'not null' property is ignored if present,
     *     as it is determined automatically by the storage controller depending
     *     on the table layout and the property definitions. It is recommended to
     *     avoid having the column definitions depend on field settings when
     *     possible. No assumptions should be made on how storage engines
     *     internally use the original column name to structure their storage.
     *   - unique keys: (optional) An array of Schema API unique key definitions.
     *     Only columns that appear in the 'columns' array are allowed.
     *   - indexes: (optional) An array of Schema API index definitions. Only
     *     columns that appear in the 'columns' array are allowed. Those indexes
     *     will be used as default indexes. Field definitions can specify
     *     additional indexes or, at their own risk, modify the default indexes
     *     specified by the field-type module. Some storage engines might not
     *     support indexes.
     *   - foreign keys: (optional) An array of Schema API foreign key
     *     definitions. Note, however, that the field data is not necessarily
     *     stored in SQL. Also, the possible usage is limited, as you cannot
     *     specify another field as related, only existing SQL tables,
     *     such as {taxonomy_term_data}.
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        return array(
            'columns' => array(
                'stripe_payment' => array(
                    'description' => 'Stripe Payment field for this entry.',
                    'type' => 'text',
                    'size' => 'small',
                    'not null' => FALSE,
                ),
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty() {
        return empty($this->get('stripe_payment')->getValue());
    }

}