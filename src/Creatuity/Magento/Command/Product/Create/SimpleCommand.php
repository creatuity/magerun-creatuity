<?php

namespace Creatuity\Magento\Command\Product\Create;

use N98\Magento\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SimpleCommand extends \N98\Magento\Command\AbstractMagentoCommand
{
    /** @var InputInterface $input */
    protected $_input;

    /** @var OutputInterface $input */
    protected $_output;

    protected $_productData = array();
    protected $_sku;
    protected $_name;
    protected $_desc;
    protected $_shortDesc;
    protected $_attributeSetId;
    protected $_attributeSet;
    protected $_type;
    protected $_qty;
    protected $_taxClassId;
    protected $_inStock;
    protected $_visibility;
    protected $_categoryIds;
    protected $_websiteIds;
    protected $_status;
    protected $_websites;
    protected $_websiteCodes;
    protected $_categories;
    protected $_image;

    protected $_mediaAttributeId;

    protected function configure()
    {
        $this
            ->setName('product:create:simple')
            ->addArgument('sku', InputArgument::REQUIRED, 'SKU')
            ->addArgument('name', InputArgument::REQUIRED, 'Product Name')
            ->addOption('desc', null, InputOption::VALUE_OPTIONAL, "Product description")
            ->addOption('shortdesc', null, InputOption::VALUE_OPTIONAL, "Product description (short)")
            ->addOption('attributeset', null, InputOption::VALUE_OPTIONAL, "Attribute Set (i.e., 'Default')")
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, "Type (i.e., 'simple')")
            ->addOption('qty', null, InputOption::VALUE_OPTIONAL, "Inventory quantity")
            ->addOption('instock', null, InputOption::VALUE_OPTIONAL, "Inventory in stock (0: No, 1: Yes)")
            ->addOption('visibility', null, InputOption::VALUE_OPTIONAL, "Visibility (none, catalog, search, both)")
            ->addOption('taxclassid', null, InputOption::VALUE_OPTIONAL, "Tax class id (i.e., 'Taxable Goods')")
            ->addOption('categoryid', null, InputOption::VALUE_OPTIONAL, "Category Id(s) (default is none, i.e. '1,2')")
            ->addOption('category', null, InputOption::VALUE_OPTIONAL, "Category name(s) (full paths)")
            ->addOption('websiteid', null, InputOption::VALUE_OPTIONAL, "Website Id(s) (default is all, i.e. '1,2')")
            ->addOption('website', null, InputOption::VALUE_OPTIONAL, "Website name(s) (i.e. 'base')")
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, "Status (0: disabled, 1: enabled)")
            ->addOption('image', null, InputOption::VALUE_OPTIONAL, "Image filename (i.e. '/import/xyz/8.jpg')")
            ->setDescription('(Experimental) Create a product.')
        ;
    }

    protected function _hasFSI()
    {
        return \Mage::helper('core')->isModuleEnabled('AvS_FastSimpleImport');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_preExecute($input, $output);

        $this->_resetEverything();

        try {
            if ($this->_hasFSI())
            {
                $this->_output->write("<info>Generating product... </info>");
                $product = array($this->_generateProduct($this->_productData));
                $this->_output->writeln("<info>Done.</info>");
                $this->_output->write("Importing product... </info>");
                $this->_importProducts($product);
                $this->_output->writeln("<info>Done.</info>");
            }
            else
            {
                $this->_output->write("<info>Creating product... </info>");
                $product = $this->_createProduct($this->_productData);
                $this->_output->writeln("<info>Done, id : " . $product->getId() . "</info>");
            }
        } catch (\Exception $e) {
            $this->_output->writeln("<error>Problem creating product: " . $e->getMessage() . "</error>");
        }
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function _preExecute(InputInterface $input, OutputInterface $output)
    {
        $this->_input = $input;
        $this->_output = $output;
        $this->detectMagento($output, true);
        $this->initMagento();
        
        $this->_attributeSet = ($this->_input->getOption('attributeset') ? $this->_input->getOption('attributeset') : 'Default');
        $this->_attributeSetId = \Mage::getModel('eav/entity_attribute_set')
            ->getCollection()
            ->setEntityTypeFilter(\Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId())
            ->addFieldToFilter('attribute_set_name', $this->_attributeSet)
            ->getFirstItem()
            ->getAttributeSetId();

        $this->_type = ($this->_input->getOption('type') ? $this->_input->getOption('type') : 'simple');

        $this->_qty = ($this->_input->getOption('qty') ? $this->_input->getOption('qty') : 42);
        $this->_inStock = ($this->_input->getOption('instock') ? $this->_input->getOption('instock') : 1);
        $taxClass = ($this->_input->getOption('taxclassid') ? $this->_input->getOption('taxclassid') : 'default');
        $this->_taxClassId = \Mage::getModel('tax/class')
            ->getCollection()
            ->addFieldToFilter('class_name',$taxClass)
            ->load()->getFirstItem()
            ->getId();
        $visibility = \Mage::getModel('catalog/product_visibility');
        $visType = ($this->_input->getOption('visibility') ? $this->_input->getOption('visibility') : 'both');
        switch (strtolower($visType))
        {
            case 'none':    $this->_visibility = $visibility::VISIBILITY_NOT_VISIBILE; break;
            case 'catalog': $this->_visibility = $visibility::VISIBILITY_IN_CATALOG; break;
            case 'search':  $this->_visibility = $visibility::VISIBILITY_IN_SEARCH; break;
            case 'both':    $this->_visibility = $visibility::VISIBILITY_BOTH; break;
        }

        $categoryIds = ($this->_input->getOption('categoryid') ? explode(',',$this->_input->getOption('categoryid')) : array());
        $websiteIds = ($this->_input->getOption('websiteid') ? explode(',',$this->_input->getOption('websiteid')) : null);
        $categories = ($this->_input->getOption('category') ? explode(',',$this->_input->getOption('category')) : array());
        $websites = ($this->_input->getOption('website') ? explode(',',$this->_input->getOption('website')) : null);

        $allWebsites = array();
        foreach (\Mage::app()->getWebsites(true) as $store)
        {
            $allWebsites[$store->getId()] = array(
                'id' => $store->getId(),
                'code' => $store->getCode(),
                'name' => $store->getName(),
            );
        }
        
        $allCategories = \Creatuity\Magento\Util\CategoryUtil::getCategories();

        if ($this->_websiteIds == null)
        {
            $this->_websiteIds = array_keys($allWebsites);
        }
        //TODO : get categories and map names to ids and vice versa, and do same for websites
        foreach ($categoryIds as $catId)
        {
            $catPath = (isset($allCategories[$catId]) ? $allCategories[$catId]['path'] : null);
            if (($catPath) &&
                (!in_array($catPath,$categories))) { $categories[] = $catPath; }
        }
        foreach ($categories as $catPath)
        {
            $catId = \Creatuity\Magento\Util\CategoryUtil::getCategoryIdByPath($catPath);
            if (($catId) &&
                (!in_array($catId, $categoryIds))) { $categoryIds[] = $catId; }
        }
        if (($websiteIds == null) && ($websites == null)) { $websites = array('Main Website'); }
        if ($websiteIds == null) { $websiteIds = array(); }
        if ($websites == null) { $websites = array(); }
        foreach ($websiteIds as $webId)
        {
            $webName = (isset($allWebsites[$webId]) ? $allWebsites[$webId]['name'] : null);
            echo "$webName\n";
            if (($webName) &&
                (!in_array($webName, $websites))) { $websites[] = $webName; }                
        }
        foreach ($websites as $webName)
        {
            $webId = \Mage::getResourceModel('core/website_collection')->addFieldToFilter('name', $webName);
            if (($webId) &&
                (!in_array($webId, $websiteIds))) {$websiteIds[] = array_shift($webId->getAllIds()); }
        }
        $this->_categoryIds = $categoryIds;
        $this->_websiteIds = $websiteIds;
        $this->_categories = $categories;
        $this->_websites = $websites;

        $this->_websiteCodes = array();
        foreach ($this->_websiteIds as $webId)
        {
            $this->_websiteCodes[] = $allWebsites[$webId]['code'];
        }
        
        $status = ($this->_input->getOption('status') ? $this->_input->getOption('status') : 1);
        $statusModel = \Mage::getModel('catalog/product_status');
        $this->_status = (($status == 1) ? $statusModel::STATUS_ENABLED : $statusModel::STATUS_DISABLED); 

        if ($this->_input->getArgument('sku')) { $this->_sku = $this->_input->getArgument('sku'); }
        if ($this->_input->getArgument('name')) { $this->_name = $this->_input->getArgument('name'); }
        if ($this->_input->getOption('desc')) { $this->_desc = $this->_input->getOption('desc'); }
        else { $this->_desc = $this->_name; }

        if ($this->_input->getOption('shortdesc')) { $this->_shortDesc = $this->_input->getOption('shortdesc'); }
        else { $this->_shortDesc = $this->_desc; }

        $this->_mediaAttributeId = \Mage::getSingleton('catalog/product')
            ->getResource()
            ->getAttribute('media_gallery')
            ->getAttributeId();

        if ($this->_input->getOption('image')) { $this->_image = $this->_input->getOption('image'); }
        else { $this->_image = ''; }
    }

    /**
     *
     */
    protected function _resetEverything()
    {
        $data = array(
        'sku' => $this->_sku,
        'name' => $this->_name,
        'desc' => $this->_desc,
        'attributeSetId' => $this->_attributeSetId,
        'attributeSet' => $this->_attributeSet,
        'type' => $this->_type,
        'weight' => 0,
        'status' => $this->_status,
        'visibility' => $this->_visibility,
        'price' => 0,
        'shortDesc' => $this->_shortDesc,
        'stockData' => array(
            'is_in_stock' => $this->_inStock,
            'qty' => $this->_qty,
            ),
        'taxClassId' => $this->_taxClassId,
        'categoryIds' => $this->_categoryIds,
        'websiteIds' => $this->_websiteIds,
        'categories' => $this->_categories,
        'websites' => $this->_websites,
        'websiteCodes' => $this->_websiteCodes,
        'image' => $this->_image,
        );

        $this->_productData = $data;
    }

    /**
     *
     */
    protected function _createProduct($data)
    {
        $product = \Mage::getModel('catalog/product');
        foreach ($data as $dataKey => $dataVal)
        {
            switch ($dataKey)
            {
                case 'sku':         $product->setSku($dataVal); break;
                case 'name':        $product->setName($dataVal); break;
                case 'desc':        $product->setDescription($dataVal); break;
                case 'attributeSetId':    $product->setAttributeSetId($dataVal); break;
                case 'type':      $product->setTypeId($dataVal); break;
                case 'weight':      $product->setWeight($dataVal); break;
                case 'status':      $product->setStatus($dataVal); break;
                case 'visibility':  $product->setVisibility($dataVal); break;
                case 'price':       $product->setPrice($dataVal); break;
                case 'shortDesc':   $product->setShortDescription($dataVal); break;
                case 'stockData':   $product->setStockData($dataVal); break;
                case 'taxClassId':  $product->setTaxClassId($dataVal); break;
                case 'categoryIds': $product->setCategoryIds($dataVal); break;
                case 'websiteIds':  $product->setWebsiteIds($dataVal); break;
                case 'status':      $product->setStatus($dataVal); break;
            }
        }

        $product->setCreatedAt(strtotime('now'));

        $product->save();
        return $product;
    }

    /**
     *
     */
    protected function _generateProduct($data)
    {
        $product = array(
            'sku'               => $data['sku'],
            '_type'             => $data['type'],
            '_attribute_set'    => $data['attributeSet'],
            '_product_websites' => $data['websiteCodes'],
            'name'              => $data['name'],
            'price'             => $data['price'],
            'description'       => $data['desc'],
            'short_description' => $data['shortDesc'],
            'weight'            => $data['weight'],
            'status'            => $data['status'],
            'visibility'        => $data['visibility'],
            'tax_class_id'      => $data['taxClassId'],
            'is_in_stock'       => $data['stockData']['is_in_stock'],
            'qty'               => $data['stockData']['qty'],
            '_category'         => $data['categories'],
            '_media_image'      => $data['image'],
            '_media_attribute_id'   => $this->_mediaAttributeId,
            '_media_is_disabled'    => 0,
            '_media_lable'          => 'image', //$data['image'], //Typo intentional, that's what Magento expects
            '_media_position'       => 1,
            'media_gallery'         => 0, //$data['image'],
            'image'                 => $data['image'],
            'small_image'           => $data['image'],
            'thumbnail'             => $data['image'],
        );

        return $product;
    }

    protected function _importProducts($data)
    {
        $import = \Mage::getModel('fastsimpleimport/import');
        $import
            ->setBehavior('append')
            ->setUseNestedArrays(true)
            ->processProductImport($data);
    }
}
