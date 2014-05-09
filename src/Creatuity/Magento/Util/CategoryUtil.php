<?php

namespace Creatuity\Magento\Util;

class CategoryUtil
{
    static function getCategories()
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
    }

    static function getCategoryIdByName($name)
    {
        $categories = self::getCategories();

        foreach ($categories as $category)
        {
            if (strcasecmp($category['name'], $name)) { return $category['id']; }
        }
        return null;
    }

}
