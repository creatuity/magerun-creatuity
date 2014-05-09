<?php

namespace Creatuity\Magento\Command\Product\Create;

use N98\Magento\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SequenceCommand extends SimpleCommand
{
    protected $_start;
    protected $_count;

    protected $_formatSKU;
    protected $_formatName;
    protected $_formatDesc;

    protected $_current;

    const MACRO_CURRENT = 0;

    protected $_macros = array(
        self::MACRO_CURRENT => '{{current}}',
    );

    protected function configure()
    {
        $this
            ->setName('product:create:sequence')
            ->addArgument('sku', InputArgument::REQUIRED, 'SKU format string')
            ->addArgument('name', InputArgument::REQUIRED, 'Product Name format string')
            ->addArgument('count', InputArgument::REQUIRED, 'Number of products to create')
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, "Starting product number (default 0)")
            ->addOption('desc', null, InputOption::VALUE_OPTIONAL, "Product description format string")
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
        
        $min = $this->_start;
        $max = $this->_start + $this->_count;
        $total = $this->_count;
        for ($i = 1; $i <= $this->_count; $i++)
        {
            $this->_current = $min+$i;

            $this->_resetEverything();
            
            try {
                $this->_output->write("<info>Creating product $i / $total... </info>");
                $product = $this->_createProduct($this->_productData);
                $this->_output->writeln("<info>Done, id : " . $product->getId() . "</info>");
            } catch (\Exception $e) {
                $this->_output->writeln("<error>Problem creating product: " . $e->getMessage() . "</error>");
            }
        }
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function _preExecute(InputInterface $input, OutputInterface $output)
    {
        parent::_preExecute($input, $output);

        if ($this->_input->getArgument('count')) { $this->_count = $this->_input->getArgument('count'); }
        $this->_start = ($this->_input->getOption('start') ? $this->_input->getOption('start') : 0);

        $this->_formatSKU = $this->_sku;
        $this->_formatName = $this->_name;
        $this->_formatDesc = $this->_desc;

        if (strpos($this->_formatSKU,'{{') === false) { $this->_formatSKU .= '{{current}}'; }
    }

    protected function _resetEverything()
    {
        $this->_sku = $this->_formatString($this->_formatSKU);
        $this->_name = $this->_formatString($this->_formatName);
        $this->_desc = $this->_formatString($this->_formatDesc);
        parent::_resetEverything();
    }

    protected function _formatString($format)
    {
        foreach ($this->_macros as $macroKey => $macroVal)
        {
            switch ($macroKey)
            {
                case self::MACRO_CURRENT:       $format = str_ireplace($macroVal, str_pad($this->_current, 10, '0', STR_PAD_LEFT), $format); break;
            }
        }
        return $format;
    }
}
