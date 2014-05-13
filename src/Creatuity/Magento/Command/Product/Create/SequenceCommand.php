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
    protected $_stop;

    protected $_formatSKU;
    protected $_formatName;
    protected $_formatDesc;
    protected $_formatShortDesc;

    protected $_current;
    protected $_offset;

    protected $_padcount;

    const MACRO_CURRENT = 0;
    const MACRO_OFFSET  = 1;
    const MACRO_START   = 2;
    const MACRO_END     = 3;
    const MACRO_COUNT   = 4;
    const MACRO_CURRENT_PAD = 5;
    const MACRO_OFFSET_PAD  = 6;
    const MACRO_START_PAD   = 7;
    const MACRO_END_PAD     = 8;
    const MACRO_COUNT_PAD   = 9;

    protected $_macros = array(
        self::MACRO_CURRENT => '{{current}}',   // Current item number (start+offset)
        self::MACRO_OFFSET  => '{{offset}}',    // Current offset from start (offset)
        self::MACRO_START   => '{{start}}',     // Starting number (start)
        self::MACRO_END     => '{{end}}',       // Ending number (stop)
        self::MACRO_COUNT   => '{{count}}',     // Total count to preform (count)
        self::MACRO_CURRENT_PAD => '{{current_pad}}',   // Current item number (start+offset), zero padded
        self::MACRO_OFFSET_PAD  => '{{offset_pad}}',    // Current offset from start (offset), zero padded
        self::MACRO_START_PAD   => '{{start_pad}}',     // Starting number (start), zero padded
        self::MACRO_END_PAD     => '{{end_pad}}',       // Ending number (stop), zero padded
        self::MACRO_COUNT_PAD   => '{{count_pad}}',     // Total count to preform (count), zero padded
    );

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('product:create:sequence')
            ->addArgument('count', InputArgument::REQUIRED, 'Number of products to create')
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, "Starting product number (default 0)")
            ->addOption('padcount', null, InputOption::VALUE_OPTIONAL, "Amount of padding for {{.._pad}} macros (default 10)")
            ->setDescription('(Experimental) Create a sequence of products.')
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
        
        $this->_end = $this->_start + $this->_count;
        $products = array();

        for ($this->_offset = 0; $this->_offset < $this->_count; $this->_offset++)
        {
            $this->_current = $this->_start+$this->_offset;

            $this->_resetEverything();
            
            try {
                if ($this->_hasFSI())
                {
                    if (($this->_offset + 1) % 1000 == 0) { $status = true; } else { $status = false; }
                    if ($status) { $this->_output->write("<info>Generating product " . ($this->_offset + 1) . " / " . $this->_count . "... </info>"); }
                    $products[] = $this->_generateProduct($this->_productData);
                    if ($status) { $this->_output->writeln("<info>Done.</info>"); }
                    if (($this->_offset == ($this->_count - 1)) || 
                        (($this->_offset + 1) % 5000 == 0))
                    {
                        $this->_output->write("Importing products... </info>");
                        $this->_importProducts($products);
                        $this->_output->writeln("<info>Done.</info>");
                        $products = array();
                    }
                }
                else
                {
                    $this->_output->write("<info>Creating product " . ($this->_offset + 1) . " / " . $this->_count . "... </info>");
                    $product = $this->_createProduct($this->_productData);
                    $this->_output->writeln("<info>Done, id : " . $product->getId() . "</info>");
                }
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
        $this->_formatShortDesc = $this->_shortDesc;

        if (strpos($this->_formatSKU,'{{') === false) { $this->_formatSKU .= '{{current_pad}}'; }

        if ($this->_input->getOption('padcount')) { $this->_padcount = $this->_input->getOption('padcount'); }
        else { $this->_padcount = 10; }
    }

    protected function _resetEverything()
    {
        $this->_sku = $this->_formatString($this->_formatSKU);
        $this->_name = $this->_formatString($this->_formatName);
        $this->_desc = $this->_formatString($this->_formatDesc);
        $this->_shortDesc = $this->_formatString($this->_formatShortDesc);
        parent::_resetEverything();
    }

    protected function _formatString($format)
    {
        foreach ($this->_macros as $macroKey => $macroVal)
        {
            switch ($macroKey)
            {
                case self::MACRO_CURRENT:       $format = str_ireplace($macroVal, $this->_current, $format); break;
                case self::MACRO_OFFSET:        $format = str_ireplace($macroVal, $this->_offset, $format); break;
                case self::MACRO_START:         $format = str_ireplace($macroVal, $this->_start, $format); break;
                case self::MACRO_END:           $format = str_ireplace($macroVal, $this->_end, $format); break;
                case self::MACRO_COUNT:         $format = str_ireplace($macroVal, $this->_count, $format); break;
                case self::MACRO_CURRENT_PAD:   $format = str_ireplace($macroVal, str_pad($this->_current, $this->_padcount, '0', STR_PAD_LEFT), $format); break;
                case self::MACRO_OFFSET_PAD:    $format = str_ireplace($macroVal, str_pad($this->_offset, $this->_padcount, '0', STR_PAD_LEFT), $format); break;
                case self::MACRO_START_PAD:     $format = str_ireplace($macroVal, str_pad($this->_start, $this->_padcount, '0', STR_PAD_LEFT), $format); break;
                case self::MACRO_END_PAD:       $format = str_ireplace($macroVal, str_pad($this->_end, $this->_padcount, '0', STR_PAD_LEFT), $format); break;
                case self::MACRO_COUNT_PAD:     $format = str_ireplace($macroVal, str_pad($this->_count, $this->_padcount, '0', STR_PAD_LEFT), $format); break;
            }
        }
        return $format;
    }
}
