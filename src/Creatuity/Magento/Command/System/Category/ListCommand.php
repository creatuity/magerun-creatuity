<?php

namespace Creatuity\Magento\Command\System\Category;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use N98\Util\Console\Helper\Table\Renderer\RendererFactory;

class ListCommand extends AbstractMagentoCommand
{
    /**
     * @var array
     */
    protected $infos;

    protected function configure()
    {
        $this
            ->setName('sys:category:list')
            ->setDescription('Lists all categories')
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Output Format. One of [' . implode(',', RendererFactory::getFormats()) . ']'
            )
        ;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output, true);

        if ($input->getOption('format') === null) {
            $this->writeSection($output, 'Magento Categories');
        }
        $this->initMagento();
        
        $table = array();

        $table = \Creatuity\Magento\Util\CategoryUtil::getCategories();

        ksort($table);
        $this->getHelper('table')
            ->setHeaders(array('Id', 'ParentId', 'Name', 'URL Key', 'URL', 'Path'))
            ->renderByFormat($output, $table, $input->getOption('format'));
    }
/*
    protected function _getCategories()
    {
        $tree = \Mage::getModel('catalog/category')->getTreeModel();
        $tree->load();

        $categories = array();
        $nodeIds = $tree->getCollection()->getAllIds();
        if ($nodeIds)
        {
            foreach ($nodeIds as $nodeId)
            {
                $category = \Mage::getModel('catalog/category');
                $category->load($nodeId);

                $categories[$category->getId()] = array(
                    'id' => $category->getId(),
                    'parentId' => $category->getParentId(),
                    'name' => $category->getName(),
                    'urlKey' => $category->getUrlKey(),
                    'urlPath' => $category->getUrlPath(),
                );
            }
        }

        return $categories;

    }*/
}

