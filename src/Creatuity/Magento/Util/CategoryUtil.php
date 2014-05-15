<?php

namespace Creatuity\Magento\Util;

class CategoryUtil
{
    // No need to load things more than once per run, so we store things in static vars
    static protected $_pathed = false;
    static protected $_categories;

    /**
     * Gets all available categories and their various details into convenient associative array
     * The $nopath flag is to prevent recursion when getCategoryPath calls us
     */
    static function getCategories($nopath = false)
    {
        // If path doesn't matter or is already determined, and we have already generated
        // return the categories
        if (($nopath || self::$_pathed) && (self::$_categories != null))
        { return self::$_categories; }

        // If we haven't generated yet, generate the categories
        if (self::$_categories == null)
        {
            // Get the category tree
            $tree = \Mage::getModel('catalog/category')->getTreeModel();
            $tree->load();

            $categories = array();
            // Flatten and turn into associative array
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

            // Go ahead and set this, so that we don't have to do so repeatedly
            // when getCategoryPath calls back into us (instead the code further
            // up will return what we have without paths so far)
            self::$_categories = $categories;
        }

        // If path information was requested and we haven't done it yet
        if (!$nopath && !self::$_pathed)
        {
            // Get paths and store them for each category
            foreach ($categories as $catKey => $catVal)
            {
                $categories[$catKey]['path'] = self::getCategoryPath($catVal['id']);
            }
            self::$_pathed = true;
        }
        
        self::$_categories = $categories;
        return $categories;
    }

    /**
     * Gets a category Id by name. If there's more than one match, first one wins.
     */
    static function getCategoryIdByName($name)
    {
        $categories = self::getCategories(true);

        foreach ($categories as $category)
        {
            if (strcasecmp($category['name'], $name)) { return $category['id']; }
        }
        return null;
    }

    /**
     * Gets a category Id by path. If there's more than one match, first one wins.
     */
    static function getCategoryIdByPath($path)
    {
        $categories = self::getCategories();

        foreach ($categories as $category)
        {
            if ($category['path'] == $path) { return $category['id']; }
        }

        return null;
    }

    /**
     * Gets a category path by name. If there's more than one match, first one wins.
     */
    static function getCategoryPathByName($name)
    {
        return self::getCategoryPath(self::getCategoryIdByName($name));
    }

    /**
     * Gets a category path by id.
     */
    static function getCategoryPath($id)
    {
        // Make sure we don't infinitely recurse by disabling path generation
        // by passing in true to getCategories
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
