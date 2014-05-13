<?php

namespace Creatuity\Magento\Util;

class CategoryUtil
{
    static protected $_pathed = false;
    static protected $_categories;

    static function getCategories($nopath = false)
    {
        if (($nopath || self::$_pathed) && (self::$_categories != null))
        { return self::$_categories; }

        if (self::$_categories == null)
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
        }
        if (!$nopath && !self::$_pathed)
        {
            foreach ($categories as $catKey => $catVal)
            {
                $categories[$catKey]['path'] = self::getCategoryPath($catVal['id']);
            }
            self::$_pathed = true;
        }
        
        self::$_categories = $categories;
        return $categories;
    }

    static function getCategoryIdByName($name)
    {
        $categories = self::getCategories(true);

        foreach ($categories as $category)
        {
            if (strcasecmp($category['name'], $name)) { return $category['id']; }
        }
        return null;
    }

    static function getCategoryIdByPath($path)
    {
        $categories = self::getCategories();

        foreach ($categories as $category)
        {
            if ($category['path'] == $path) { return $category['id']; }
        }

        return null;
    }

    static function getCategoryPathByName($name)
    {
        return self::getCategoryPath(self::getCategoryIdByName($name));
    }
    static function getCategoryPath($id)
    {
        $categories = self::getCategories(true);
        $path = array();

        while ($categories[$id]['parentId'] > 1)
        {
            array_unshift($path,$categories[$id]['name']);
            $id = $categories[$id]['parentId'];
        }

        return implode('/',$path);

    }

}
