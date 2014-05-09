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
    protected $_type;
    protected $_qty;
    protected $_taxClassId;
    protected $_inStock;
    protected $_visibility;
    protected $_categoryIds;
    protected $_websiteIds;
    protected $_status;

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
            ->addOption('categoryid', null, InputOption::VALUE_OPTIONAL, "Category Id(s) (i.e. '1,2')")
            ->addOption('websiteid', null, InputOption::VALUE_OPTIONAL, "Website Id(s) (i.e. '1,2')")
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, "Status (0: disabled, 1: enabled)")
            ->setDescription('(Experimental) Create a product.')
        ;
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
            $this->_output->write("<info>Creating product... </info>");
            $product = $this->_createProduct($this->_productData);
            $this->_output->writeln("<info>Done, id : " . $product->getId() . "</info>");
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
        
        $attributeSet = ($this->_input->getOption('attributeset') ? $this->_input->getOption('attributeset') : 'Default');
        $this->_attributeSetId = \Mage::getModel('eav/entity_attribute_set')
            ->getCollection()
            ->setEntityTypeFilter(\Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId())
            ->addFieldToFilter('attribute_set_name', $attributeSet)
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

        $this->_categoryIds = ($this->_input->getOption('categoryid') ? explode(',',$this->_input->getOption('categoryid')) : array());
        $this->_websiteIds = ($this->_input->getOption('websiteid') ? explode(',',$this->_input->getOption('websiteid')) : array());

        
        $status = ($this->_input->getOption('status') ? $this->_input->getOption('status') : 1);
        $statusModel = \Mage::getModel('catalog/product_status');
        $this->_status = (($status == 1) ? $statusModel::STATUS_ENABLED : $statusModel::STATUS_DISABLED); 

        if ($this->_input->getArgument('sku')) { $this->_sku = $this->_input->getArgument('sku'); }
        if ($this->_input->getArgument('name')) { $this->_name = $this->_input->getArgument('name'); }
        if ($this->_input->getOption('desc')) { $this->_desc = $this->_input->getOption('desc'); }
        else { $this->_desc = $this->_name; }

        if ($this->_input->getOption('shortdesc')) { $this->_shortDesc = $this->_input->getOption('shortdesc'); }
        else { $this->_shortDesc = $this->_desc; }

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
}
