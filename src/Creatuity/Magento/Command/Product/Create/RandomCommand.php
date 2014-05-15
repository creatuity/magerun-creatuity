<?php

namespace Creatuity\Magento\Command\Product\Create;

use N98\Magento\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RandomCommand extends SequenceCommand
{

    protected $_randText;
    protected $_randImage;
    protected $_randCategory;
    protected $_randWebsite;
    protected $_textLimit;
    protected $_imageLimit;
    protected $_catLimit;
    protected $_siteLimit;

    // Additional macro expansion for random text
    const MACRO_RANDTEXT    = 100;

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('product:create:random')
            ->addOption('randtext', null, InputOption::VALUE_OPTIONAL, "List of words to choose from randomly for {{randtext}}, or file containing list (defaults to random Lorem")
            ->addOption('randimages', null, InputOption::VALUE_OPTIONAL, "List of images to choose from randomly, or file containing list")
            ->addOption('textlimit', null, InputOption::VALUE_OPTIONAL, "Sets limit for number of words used to fill {{randtext}} (default 5)")
            ->addOption('imagelimit', null, InputOption::VALUE_OPTIONAL, "Sets limit for number of images per product (default 3)")
            ->addOption('catlimit', null, InputOption::VALUE_OPTIONAL, "Sets limit for number of categories a product can be in (default 3)")
            ->addOption('sitelimit', null, InputOption::VALUE_OPTIONAL, "Sets limit for number of websites a product can be in (default 3)")
            ->setDescription('(Experimental) Create a sequence of products with random details.')
        ;

        // Adding additional macro expansion string here, 
        // since we can't do $x = array_merge(parent::$x,array(...)) in class definition
        $this->_macros[self::MACRO_RANDTEXT] = '{{randtext}}';
    }

    /**
     * Load list from string
     * $fn is filename containing list to load, or comma separated list
     * Returns array or null
     */
    protected function _listFile($fn)
    {
        // If there's no commas, and file exists.. assume list file, not image
        if ((strpos($fn,',') === false) && (file_exists($fn)))
        {
            $list = file_get_contents($fn);
            $list = str_replace("\r\n", "\n", $list);
            $list = str_replace("\r", "\n", $list);
            return array_values(array_filter(explode("\n", $list)));
        }
        else
        {
            return explode(",", $fn);
        }
        return null;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function _preExecute(InputInterface $input, OutputInterface $output)
    {
        parent::_preExecute($input, $output);

        $this->_randText = ($this->_input->getOption('randtext') ? $this->_listFile($this->_input->getOption('randtext')) : null);
        $this->_randImage = ($this->_input->getOption('randimages') ? $this->_listFile($this->_input->getOption('randimages')) : null);
        
        $this->_textLimit = ($this->_input->getOption('textlimit') ? $this->_input->getOption('textlimit') : 5);
        $this->_imageLimit = ($this->_input->getOption('imagelimit') ? $this->_input->getOption('imagelimit') : 3);
        $this->_catLimit = ($this->_input->getOption('catlimit') ? $this->_input->getOption('catlimit') : 3);
        $this->_siteLimit = ($this->_input->getOption('sitelimit') ? $this->_input->getOption('sitelimit') : 3);

        // If we weren't provided any words, generate some
        if ($this->_randText == null)
        {
            $words = file_get_contents('http://loripsum.net/api/100/long/plaintext');
            $words = array_values(array_filter(array_unique(explode(' ',preg_replace( '/\s+/', ' ', $words)))));
            $this->_randText = $words;
        }

        // Override default no categories behavior with all if none are specified
        if (count($this->_categoryIds) == 0)
        {
            $this->_categoryIds = null; 
            $this->_categories = $this->_mapCategories($this->_categoryIds, $this->_categories);
            $this->_categoryIds = $this->_mapCategoryIds($this->_categories, $this->_categoryIds);
        }
        $this->_randCategory = $this->_categoryIds;
        $this->_randWebsite = $this->_websiteIds;
    }

    /**
     * Gets a random number of words from the random word list
     */
    protected function _getRandText()
    {
        $text = '';
        $max = mt_rand(1,$this->_textLimit);
        for ($i = 0; $i < $max; $i++)
        {
            $rand = mt_rand(0,count($this->_randText)-1);
            
            $text .= ' ' . $this->_randText[$rand];
        }
        return trim($text);
    }

    /**
     * Simple macro expansion / replacement function
     */
    protected function _formatString($format)
    {
        // Loop over each available type of macro
        foreach ($this->_macros as $macroKey => $macroVal)
        {
            // Switch on the type and process
            switch ($macroKey)
            {
                case self::MACRO_RANDTEXT:    $format = str_ireplace($macroVal, $this->_getRandText(), $format); break;
                default:                break;
            }
        }
        // Call down to parent implementation for further processing
        return parent::_formatString($format);
    }

    /**
     * Set product attributes based on the configuration
     */
    protected function _resetEverything()
    {
        // Call the parent implementation
        parent::_resetEverything();

        // Random text is handled by _formatString and {{randtext}}

        // Random images, if we have a source of images
        if (count($this->_randImage) > 0)
        {
            $imageExtra = array();
            $max = mt_rand(1,$this->_imageLimit);
            // Loop up to _imageLimit times to get images
            for ($i = 0; $i < $max; $i++)
            {
                // Get a random image and fill out the other various columns
                $rand = mt_rand(0,count($this->_randImage)-1);
                $imageExtra['_media_image'][] = $this->_randImage[$rand];
                $imageExtra['_media_attribute_id'][] = $this->_mediaAttributeId;
                $imageExtra['_media_position'][] = $i+1;
                $imageExtra['_media_is_disabled'][] = 0;
                $imageExtra['_media_lable'][] = "Image $i";
            }            
            $imageExtra['_media_is_disabled'][0] = 1;

            // Set image, small_image, and thumbnail to random selections from the generated list
            // May end up being all the same or not.. 
            $imageExtra['image'] = $imageExtra['_media_image'][mt_rand(0,count($imageExtra['_media_image'])-1)];
            $imageExtra['small_image'] = $imageExtra['_media_image'][mt_rand(0,count($imageExtra['_media_image'])-1)];
            $imageExtra['thumbnail'] = $imageExtra['_media_image'][mt_rand(0,count($imageExtra['_media_image'])-1)];

            // Set _imageExtra for later merging by _generateProduct
            $this->_imageExtra = $imageExtra;
        }

        // Random categories, if we have any to choose from
        if (count($this->_randCategory) > 0)
        {
            // Get random list of Ids, then force the categories to be remapped from it
            $this->_categoryIds = $this->_randomList($this->_randCategory, $this->_catLimit);
            $this->_categories = $this->_mapCategories($this->_categoryIds, array());
        }

        // Random websites, if we have any to choose from
        if (count($this->_randWebsite) > 0)
        {
            // Get random list of Ids, then force the websites to be remapped from it
            $this->_websiteIds = $this->_randomList($this->_randWebsite, $this->_siteLimit);
            $this->_websites = $this->_mapWebsites($this->_websiteIds, array());
        }

    }

    /**
     * Generate a list
     * List will be between $min and $max in size,
     * of random values from $source
     */
    protected function _randomList($source, $max, $min=1)
    {
        $max = min(count($source), mt_rand($min,$max));
        $list = array();
        // We don't want repeats, but if we can't get unique values
        // in less than this many tries, we'll just fail out with
        // what we have so far
        $failsafeTries = 10;
        $failsafe = $failsafeTries;
        $i = 0;
        while (($i < $max) && ($failsafe > 0))
        {
            // Generate a random entry
            $rand = mt_rand(0, count($source) - 1);
            if (!in_array($source[$rand], $list))
            {
                // Not in list yet, add it, and reset failsafe
                $list[] = $source[$rand];
                $i++;
                $failsafe=$failsafeTries;
            }
            else
            {
                // We already have this entry, decrement failsafe
                $failsafe--;
            }
        }
        return $list;
    }

}
