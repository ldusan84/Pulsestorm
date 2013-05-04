<?php

require_once 'abstract.php';

class Mage_Shell_Attribute extends Mage_Shell_Abstract
{
    /**
     * Run script
     *
     */
    public function run()
    {
        var_dump($this->_args);
        echo $this->_getMigrationScriptForAttribute($this->_args['attribute_code']);
    }

    /**
     * Additional initialize instruction
     *
     * (Copied from bootstrap method)
     *
     * @return Mage_Shell_Abstract
     */
    protected function _construct()
    {
        /**
         * Error reporting
         */
        error_reporting(E_ALL | E_STRICT);

        $mageFilename = '../app/Mage.php';
        $maintenanceFile = 'maintenance.flag';
        require_once $mageFilename;

        #Varien_Profiler::enable();

        Mage::setIsDeveloperMode(true);

        ini_set('display_errors', 1);

        umask(0);

        /* Store or website code */
        $mageRunCode = isset($_SERVER['MAGE_RUN_CODE']) ? $_SERVER['MAGE_RUN_CODE'] : '';

        /* Run store or run website */
        $mageRunType = isset($_SERVER['MAGE_RUN_TYPE']) ? $_SERVER['MAGE_RUN_TYPE'] : 'store';

        if (method_exists('Mage', 'init')) {
            Mage::init($mageRunCode, $mageRunType);
        } else {
            Mage::app($mageRunCode, $mageRunType);
        }

        return $this;
    }

    protected function  _getOptionArrayForAttribute($attribute)
    {
        $read   = Mage::getModel('core/resource')->getConnection('core_read');
        $select = $read->select()
            ->from('eav_attribute_option')
            ->join('eav_attribute_option_value','eav_attribute_option.option_id=eav_attribute_option_value.option_id')
            ->where('attribute_id=?',$attribute->getId())
            ->where('store_id=0')
            ->order('eav_attribute_option_value.option_id');

        $query = $select->query();

        $values = array();
        foreach ($query->fetchAll() as $rows) {
            $values[] = $rows['value'];
        }

        return array('values'=>$values);
    }

    protected function _getKeyLegend()
    {
        return array(
                //catalog
                'frontend_input_renderer'       => 'input_renderer',
                'is_global'                     => 'global',
                'is_visible'                    => 'visible',
                'is_searchable'                 => 'searchable',
                'is_filterable'                 => 'filterable',
                'is_comparable'                 => 'comparable',
                'is_visible_on_front'           => 'visible_on_front',
                'is_wysiwyg_enabled'            => 'wysiwyg_enabled',
                'is_visible_in_advanced_search' => 'visible_in_advanced_search',
                'is_filterable_in_search'       => 'filterable_in_search',
                'is_used_for_promo_rules'       => 'used_for_promo_rules',

                'backend_model'                 => 'backend',
                'backend_type'                  => 'type',
                'backend_table'                 => 'table',
                'frontend_model'                => 'frontend',
                'frontend_input'                => 'input',
                'frontend_label'                => 'label',
                'source_model'                  => 'source',
                'is_required'                   => 'required',
                'is_user_defined'               => 'user_defined',
                'default_value'                 => 'default',
                'is_unique'                     => 'unique',
                'is_global'                     => 'global',

        );
    }

    protected function _getMigrationScriptForAttribute($code)
    {
        //load the existing attribute model
        $attribute = Mage::getModel('catalog/resource_eav_attribute')
            ->loadByCode('catalog_product', $code);

        if (!$attribute->getId()) {
            $this->_showError(sprintf("Could not find attribute [%s].", $code));
        }

        //get a map of "real attribute properties to properties used in setup resource array
        $realToSetupKeyLegend = $this->_getKeyLegend();

        //swap keys from above
        $data = $attribute->getData();
        $keysLegend = array_keys($realToSetupKeyLegend);
        $newData    = array();

        foreach ($data as $key=>$value) {
            if (in_array($key, $keysLegend)) {
                $key = $realToSetupKeyLegend[$key];
            }
            $newData[$key] = $value;
        }

        //unset items from model that we don't need and would be discarded by
        //resource script anyways
        $attributeCode = $newData['attribute_code'];
        unset($newData['attribute_id']);
        unset($newData['attribute_code']);
        unset($newData['entity_type_id']);

        //chuck a few warnings out there for things that were a little murky
        if ($newData['attribute_model']) {
            $this->_showError("//WARNING, value detected in attribute_model.  We've never seen a value there before and this script doesn't handle it.  Caution, etc. " . "\n");
        }

        if ($newData['is_used_for_price_rules']) {
            $this->_showError("//WARNING, non false value detected in is_used_for_price_rules.  The setup resource migration scripts may not support this (per 1.7.0.1)" . "\n");
        }

        //load values for attributes (if any exist)
        $newData['option'] = $this->_getOptionArrayForAttribute($attribute);

        //get text for script
        $array = var_export($newData, true);

        //generate script using simple string concatenation, making
        //a single tear fall down the cheek of a CS professor
        $script = "<?php
        if (! (\$this instanceof Mage_Catalog_Model_Resource_Setup) ) {
            throw new Exception(\"Resource Class needs to inherit from \" .
            \"Mage_Catalog_Model_Resource_Setup for this to work\");
        }

        \$attr = $array;
\$this->addAttribute('catalog_product','$attributeCode',\$attr);
";

        return $script;
    }

    protected function _showError($error)
    {
        echo $error;
    }
}

$shell = new Mage_Shell_Attribute();
$shell->run();
