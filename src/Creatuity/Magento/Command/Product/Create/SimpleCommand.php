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
    protected $_imageSmall;
    protected $_imageThumb;
    protected $_imageExtra;

    protected $_mediaAttributeId;

    protected function configure()
    {
        $this
            ->setName('product:create:simple')
            ->addArgument('sku', InputArgument::REQUIRED, 'SKU')
            ->addArgument('name', InputArgument::REQUIRED, 'Product Name')
            ->addOption('desc', null, InputOption::VALUE_OPTIONAL, "Product description (default: same as name)")
            ->addOption('shortdesc', null, InputOption::VALUE_OPTIONAL, "Product description, short (default: same as description)")
            ->addOption('attributeset', null, InputOption::VALUE_OPTIONAL, "Attribute Set (i.e., 'Default')")
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, "Type (i.e., 'simple')")
            ->addOption('qty', null, InputOption::VALUE_OPTIONAL, "Inventory quantity (default 42)")
            ->addOption('instock', null, InputOption::VALUE_OPTIONAL, "Inventory in stock (0: No, 1: Yes - default)")
            ->addOption('visibility', null, InputOption::VALUE_OPTIONAL, "Visibility (none, catalog, search, both - default)")
            ->addOption('taxclassid', null, InputOption::VALUE_OPTIONAL, "Tax class id (i.e., 'Taxable Goods', default 'default')")
            ->addOption('categoryid', null, InputOption::VALUE_OPTIONAL, "Category Id(s) (i.e. '1,2', default none or all (random))")
            ->addOption('category', null, InputOption::VALUE_OPTIONAL, "Category name(s) (full paths, same defaults as categoryid)")
            ->addOption('websiteid', null, InputOption::VALUE_OPTIONAL, "Website Id(s) (i.e. '1,2', default is all)")
            ->addOption('website', null, InputOption::VALUE_OPTIONAL, "Website name(s) (i.e. 'base', same defaults as websiteid)")
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, "Status (0: disabled, 1: enabled - default)")
            ->addOption('image', null, InputOption::VALUE_OPTIONAL, "Image filename relative to media/import (i.e. '/xyz/8.jpg')")
            ->addOption('imagesmall', null, InputOption::VALUE_OPTIONAL, "Small Image filename (i.e. '/xyz/8.jpg')")
            ->addOption('imagethumb', null, InputOption::VALUE_OPTIONAL, "Thumbnail Image filename (i.e. '/xyz/8.jpg')")
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
        // Call preExecute (separating it allows that portion to be overloaded in inherited classes)
        $this->_preExecute($input, $output);

        // Set the various attributes 
        $this->_resetEverything();

        try {
            if ($this->_hasFSI())
            {
                // Use AvS_FastSimpleImport (this is actually slower for single / smaller quantities),
                // but since it's faster for larger quantities that's the code path that has been
                // fully implemented, so we still use it here for single products.
                $this->_output->write("<info>Generating product... </info>");
                $product = array($this->_generateProduct($this->_productData));
                $this->_output->writeln("<info>Done.</info>");
                $this->_output->write("Importing product... </info>");
                $this->_importProducts($product);
                $this->_output->writeln("<info>Done.</info>");
            }
            else
            {
                // Use regular Magento product creation. This path is not fully implemented yet.
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
        
        // EAV attribute set for products
        $this->_attributeSet = ($this->_input->getOption('attributeset') ? $this->_input->getOption('attributeset') : 'Default');
        $this->_attributeSetId = \Mage::getModel('eav/entity_attribute_set')
            ->getCollection()
            ->setEntityTypeFilter(\Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId())
            ->addFieldToFilter('attribute_set_name', $this->_attributeSet)
            ->getFirstItem()
            ->getAttributeSetId();

        // Product type (currently only 'simple' is handled)
        $this->_type = ($this->_input->getOption('type') ? $this->_input->getOption('type') : 'simple');

        // Product stock (quantity and in stock status)
        $this->_qty = ($this->_input->getOption('qty') ? $this->_input->getOption('qty') : 42);
        $this->_inStock = ($this->_input->getOption('instock') ? $this->_input->getOption('instock') : 1);

        // Tax Class
        $taxClass = ($this->_input->getOption('taxclassid') ? $this->_input->getOption('taxclassid') : 'default');
        $this->_taxClassId = \Mage::getModel('tax/class')
            ->getCollection()
            ->addFieldToFilter('class_name',$taxClass)
            ->load()->getFirstItem()
            ->getId();

        // Product visibility
        $visibility = \Mage::getModel('catalog/product_visibility');
        $visType = ($this->_input->getOption('visibility') ? $this->_input->getOption('visibility') : 'both');
        switch (strtolower($visType))
        {
            case 'none':    $this->_visibility = $visibility::VISIBILITY_NOT_VISIBILE; break;
            case 'catalog': $this->_visibility = $visibility::VISIBILITY_IN_CATALOG; break;
            case 'search':  $this->_visibility = $visibility::VISIBILITY_IN_SEARCH; break;
            case 'both':    $this->_visibility = $visibility::VISIBILITY_BOTH; break;
        }

        // Category and Website to create products in (we map the id and name variants across to each other,
        // so it doesn't matter how you specify them, you can even mix them)
        $categoryIds = ($this->_input->getOption('categoryid') ? explode(',',$this->_input->getOption('categoryid')) : array());
        $websiteIds = ($this->_input->getOption('websiteid') ? explode(',',$this->_input->getOption('websiteid')) : null);
        $categories = ($this->_input->getOption('category') ? explode(',',$this->_input->getOption('category')) : array());
        $websites = ($this->_input->getOption('website') ? explode(',',$this->_input->getOption('website')) : null);

        // Map the Ids into category names, and vice versa
        $categories = $this->_mapCategories($categoryIds, $categories);
        $categoryIds = $this->_mapCategoryIds($categories, $categoryIds);
        // Ditto
        $websites = $this->_mapWebsites($websiteIds, $websites);
        $websiteIds = $this->_mapWebsiteIds($websites, $websiteIds);

        $this->_categoryIds = $categoryIds;
        $this->_websiteIds = $websiteIds;
        $this->_categories = $categories;
        $this->_websites = $websites;
        
        // Product status
        $status = ($this->_input->getOption('status') ? $this->_input->getOption('status') : 1);
        $statusModel = \Mage::getModel('catalog/product_status');
        $this->_status = (($status == 1) ? $statusModel::STATUS_ENABLED : $statusModel::STATUS_DISABLED); 

        // Get SKU, name, description
        if ($this->_input->getArgument('sku')) { $this->_sku = $this->_input->getArgument('sku'); }
        if ($this->_input->getArgument('name')) { $this->_name = $this->_input->getArgument('name'); }
        if ($this->_input->getOption('desc')) { $this->_desc = $this->_input->getOption('desc'); }
        else { $this->_desc = $this->_name; }

        // Get short description, if non specified, use description.
        if ($this->_input->getOption('shortdesc')) { $this->_shortDesc = $this->_input->getOption('shortdesc'); }
        else { $this->_shortDesc = $this->_desc; }

        // Get image media attribute id 
        $this->_mediaAttributeId = \Mage::getSingleton('catalog/product')
            ->getResource()
            ->getAttribute('media_gallery')
            ->getAttributeId();
        
        // Get image, small image, and thumbnail filenames
        if ($this->_input->getOption('image')) { $this->_image = $this->_input->getOption('image'); }
        else { $this->_image = ''; }

        $this->_imageSmall = ($this->_input->getOption('imagesmall')) ? $this->_input->getOption('imagesmall') : $this->_image;
        $this->_imageThumb = ($this->_input->getOption('imagethumb')) ? $this->_input->getOption('imagethumb') : $this->_image;
    }

    /**
     * Maps the category IDs into category names
     */
    protected function _mapCategories($categoryIds,$categories)
    {
        // Get all the categories
        $allCategories = \Creatuity\Magento\Util\CategoryUtil::getCategories();

        // If we had null for the categoryIds, we're going to grab all of them
        // This is to support random generator's default behavior of all,
        // where the simple and sequence generators default to none
        if ($categoryIds == null)
        {
            $categoryIds = array_keys($allCategories);
        }

        foreach ($categoryIds as $catId)
        {
            $catPath = (isset($allCategories[$catId]) ? $allCategories[$catId]['path'] : null);
            if (($catPath) &&
                (!in_array($catPath,$categories))) { $categories[] = $catPath; }
        }
        return $categories;
    }
    
    /**
     * Maps the category names into IDs
     */
    protected function _mapCategoryIds($categories,$categoryIds)
    {
        foreach ($categories as $catPath)
        {
            $catId = \Creatuity\Magento\Util\CategoryUtil::getCategoryIdByPath($catPath);
            if (($catId) &&
                (!in_array($catId, $categoryIds))) { $categoryIds[] = $catId; }
        }
        return $categoryIds;
    }
    
    /**
     * Maps the website IDs into website names
     * Also handles setting the website codes array
     */
    protected function _mapWebsites($websiteIds, $websites)
    {
        $allWebsites = array();

        // We can't really be adding products to admin, can we?
        // We can add others here if we need to (possibly from a command line
        // option), but that hasn't been implemented yet.
        $blacklist = array('admin');
        // Get all websites, filtering against the blacklist
        foreach (\Mage::app()->getWebsites(true) as $store)
        {
            if (!in_array($store->getCode(), $blacklist))
            {
                $allWebsites[$store->getId()] = array(
                    'id' => $store->getId(),
                    'code' => $store->getCode(),
                    'name' => $store->getName(),
                );
            }
        }

        // Generate the website codes array
        $this->_websiteCodes = array();
        foreach ($this->_websiteIds as $webId)
        {
            $this->_websiteCodes[] = $allWebsites[$webId]['code'];
        }

        // If websiteIds is null, we get all
        // Since we run this first then _mapWebsiteIds, the result is both get all
        if ($websiteIds == null)
        {
            $websiteIds = array_keys($allWebsites);
        }

        foreach ($websiteIds as $webId)
        {
            $webName = (isset($allWebsites[$webId]) ? $allWebsites[$webId]['name'] : null);
            if (($webName) &&
                (!in_array($webName, $websites))) { $websites[] = $webName; }
        }
        return $websites;
    }
    
    /**
     * Maps the website names into website IDs
     */
    protected function _mapWebsiteIds($websites, $websiteIds)
    {
        foreach ($websites as $webName)
        {
            $webId = \Mage::getResourceModel('core/website_collection')->addFieldToFilter('name', $webName);
            if (($webId) &&
                (!in_array($webId, $websiteIds))) {$websiteIds[] = array_shift($webId->getAllIds()); }
        }
        return $websiteIds;
    }

    /**
     * Set product attributes based on the configuration
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
        'imageSmall' => $this->_imageSmall,
        'imageThumb' => $this->_imageThumb,
        );

        // Make sure this is a valid array
        // Currently only used by random generator
        $this->_imageExtra = array();

        $this->_productData = $data;
    }

    /**
     * Create product using Magento product model 
     * TODO: Images, and other things ...
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
     * Create product to be imported using AvS_FastSimpleImport
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
            'small_image'           => $data['imageSmall'],
            'thumbnail'             => $data['imageThumb'],
        );
        // _imageExtra is to make it easier for the random generator 
        // to generate data that gets pulled in here, without messing
        // with the existing inherited code flow too much.. 
        return array_merge($product, $this->_imageExtra);
    }

    /**
     * Import array of products using AvS_FastSimpleImport
     */
    protected function _importProducts($data)
    {
        $import = \Mage::getModel('fastsimpleimport/import');
        $import
            ->setBehavior('append')
            ->setUseNestedArrays(true)
            ->processProductImport($data);
    }
}
