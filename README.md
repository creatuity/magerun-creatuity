Magerun Addons by Creatuity
===========================

Magerun Addons developed by Creatuity (loosely based on kalenjordan/magerun-addons)

Some additional comands for the excellent N98-MageRun Magento command-line tool.

We had need of some commands that didn't exist, so we created them.

* Create simple product
* Create sequence of products 
* Create sequence of products with randomized attributes
* List Categories

Requirements
------------
While this will (somewhat) function with vanilla Magerun (obviously) and Magento, currently much of the functionality (images and such) are not yet implemented in the standard Magento codepath. Upon discovering how terribly slow it was and how much faster (20x+ in our testing) the Magento extension AvS_FastSimpleImport is, development was switched to using that. We intend to flesh out the vanilla implementation but it is a low priority.

Magerun: https://github.com/netz98/n98-magerun

AvS\_FastSimpleImport: https://github.com/avstudnitz/AvS_FastSimpleImport

Installation
------------
### Using composer ###
1. Add the repository to your `composer.json` file under the `require` node.

        "creatuity/magerun-creatuity": "dev-master"
    
    to your `composer.json` file.

2. Update composer from within your n98-magerun root

        php composer.phar update

### Using ~/.n98-magerun/modules/ ###
1. Create `~/.n98-magerun/modules/`

2. Clone this repository to `~/.n98-magerun/modules/`

        cd ~/.n98-magerun/modules/
        git clone https://github.com/creatuity/magerun-creatuity.git

Commands
--------

### Create simple product ###

This (experimental) command will generate a single simple product, using the supplied SKU and product name, and has a number of options for more fine tuning the resulting product.

        $ magerun product:create:simple [sku] [name]

While not particularly useful by itself, it is the building block for the more useful sequence and random product generators. When specifying images, be sure to have a leading slash, and the path is relative to media/import.

Option   | Description                                
:------- | :------------------------------------------
`--desc` | Product description (default: same as name)
`--shortdesc` | Product description, short (default: same as description)
`--attributeset` | Attribute Set (i.e., 'Default')
`--type` | Type (i.e., 'simple')
`--instock` |          Inventory in stock (0: No, 1: Yes - default)
`--visibility` |       Visibility (none, catalog, search, both - default)
`--taxclassid` |       Tax class id (i.e., 'Taxable Goods', default 'default')
`--categoryid` |       Category Id(s) (i.e. '1,2', default none)
`--category` |         Category name(s) (full paths, same defaults as categoryid)
`--websiteid` |        Website Id(s) (i.e. '1,2', default is all)
`--website` |          Website name(s) (i.e. 'base', same defaults as websiteid)
`--status` |           Status (0: disabled, 1: enabled - default)
`--image` |            Image filename relative to media/import (i.e. '/xyz/8.jpg')
`--imagesmall` |       Small Image filename (i.e. '/xyz/8.jpg')
`--imagethumb` | Thumbnail Image filename (i.e. '/xyz/8.jpg')

### Create sequence of products ###

This (experimental) command will generate a sequence of simple products, using the supplied SKU template and product name, and has a number of options for more fine tuning the resulting product.

        $ magerun product:create:sequence [sku] [name] [count]
        
This inherits functionality from `product:create:simple`, and expands upon it. Notably, there is an additional required argument `count` which is the number of products to create, and an option `--start` which allows you to start counting from an arbitrary number.

For example, to create a sequence of products from 100001 to 200000 (starting at 100001 and 100000 products):

        $ magerun product:create:sequence 'SKU{{current_pad}}' 'Product {{current}}' 100000 --start 100001

To make automatic generation possible and yet flexible, a simple macro expansion system is added (mainly so that SKUs can be automatically generated, but you can use it in various other ways). If you don't supply any macro to the SKU, then `{{current_pad}}` is automatically appended to it. Currently, only the SKU, name, description, and short description are parsed for macro expansion.

Macro | Description
:---- | :-----------
{{current}} | Current product number (starting number + number of generated item)
{{offset}} | Current offset from start of sequence (number of currently generated item)
{{start}} | Starting number of sequence (doesn't change during a run)
{{end}} |  Ending number of sequence (doesn't change during a run)
{{count}} | Total number of items in sequence to be generated (doesn't change during a run)
{{current_pad}} | Current product number (starting number + number of generated item), zero padded
{{offset_pad}} | Current offset from start of sequence (number of currently generated item), zero padded
{{start_pad}} | Starting number of sequence (doesn't change during a run), zero padded
{{end_pad}} |  Ending number of sequence (doesn't change during a run), zero padded
{{count_pad}} | Total number of items in sequence to be generated (doesn't change during a run), zero padded

The amount of zero padding for the `{{..._pad}}` variety of macros can be adjusted with the `--padcount` option (defaults to 10 places). 

Option   | Description                                
:------- | :------------------------------------------
`--desc` | Product description (default: same as name)
`--shortdesc` | Product description, short (default: same as description)
`--attributeset` | Attribute Set (i.e., 'Default')
`--type` | Type (i.e., 'simple')
`--instock` |          Inventory in stock (0: No, 1: Yes - default)
`--visibility` |       Visibility (none, catalog, search, both - default)
`--taxclassid` |       Tax class id (i.e., 'Taxable Goods', default 'default')
`--categoryid` |       Category Id(s) (i.e. '1,2', default none)
`--category` |         Category name(s) (full paths, same defaults as categoryid)
`--websiteid` |        Website Id(s) (i.e. '1,2', default is all)
`--website` |          Website name(s) (i.e. 'base', same defaults as websiteid)
`--status` |           Status (0: disabled, 1: enabled - default)
`--image` |            Image filename relative to media/import (i.e. '/xyz/8.jpg')
`--imagesmall` |       Small Image filename (i.e. '/xyz/8.jpg')
`--imagethumb` | Thumbnail Image filename (i.e. '/xyz/8.jpg')
`--start`    |           Starting product number to count from (default 0)
`--padcount`  |          Amount of padding for {{.._pad}} macros (default 10)

### Create sequence of products with randomized attributes ###

This (experimental) command will generate a sequence of simple products of randomized attributes, using the supplied SKU template and product name, and has a number of options for more fine tuning the resulting product.

        $ magerun product:create:random [sku] [name] [count]
        
This inherits functionality from `product:create:sequence`, and expands upon it. Notably, there is are several new options for specifying sources of words for random text (defaults to random Lorem Ipsum from http://loripsum.net/), random images (defaults to none), random categories (defaults to all), websites (defaults to all). These randomly generated items also have a limit for each of these, which can be changed from their defaults using their corresponding options.

For example, to create a sequence of products from 100001 to 200000 (starting at 100001 and 100000 products), with random names (and therefor descriptions and short descriptions, due to how these are defaulted from each other) and images:

        $ magerun product:create:random 'SKU{{current_pad}}' '{{randtext}}' 100000 --start 100001 --randimages "media/import/images.txt"

Generating an image list from images you've placed in media/import is easy, just run from the media/import directory:

        $ find .|grep -E "(jpg|jpeg|png|gif)"| cut -c 2- > images.txt

If you want a slew of hilarious placeholder images, you can git clone any number of these under media/import before running the above command, though it will pick up one or two non placeholder images out of the repos :
* https://github.com/davecowart/placecage
* https://github.com/davecowart/stevensegallery
* https://github.com/davecowart/fill-murray
* https://github.com/ChrisMissal/kevinspacer

To make automatic generation possible and yet flexible, a simple macro expansion system is added (mainly so that SKUs can be automatically generated, but you can use it in various other ways). If you don't supply any macro to the SKU, then `{{current_pad}}` is automatically appended to it. Currently, only the SKU, name, description, and short description are parsed for macro expansion.

Macro | Description
:---- | :-----------
{{current}} | Current product number (starting number + number of generated item)
{{offset}} | Current offset from start of sequence (number of currently generated item)
{{start}} | Starting number of sequence (doesn't change during a run)
{{end}} |  Ending number of sequence (doesn't change during a run)
{{count}} | Total number of items in sequence to be generated (doesn't change during a run)
{{current_pad}} | Current product number (starting number + number of generated item), zero padded
{{offset_pad}} | Current offset from start of sequence (number of currently generated item), zero padded
{{start_pad}} | Starting number of sequence (doesn't change during a run), zero padded
{{end_pad}} |  Ending number of sequence (doesn't change during a run), zero padded
{{count_pad}} | Total number of items in sequence to be generated (doesn't change during a run), zero padded
{{randtext}} | Random text of up to `--textlimit` items
The amount of zero padding for the `{{..._pad}}` variety of macros can be adjusted with the `--padcount` option (defaults to 10 places). 

Option   | Description                                
:------- | :------------------------------------------
`--desc` | Product description (default: same as name)
`--shortdesc` | Product description, short (default: same as description)
`--attributeset` | Attribute Set (i.e., 'Default')
`--type` | Type (i.e., 'simple')
`--instock` |          Inventory in stock (0: No, 1: Yes - default)
`--visibility` |       Visibility (none, catalog, search, both - default)
`--taxclassid` |       Tax class id (i.e., 'Taxable Goods', default 'default')
`--categoryid` |       Category Id(s) (i.e. '1,2', default all)
`--category` |         Category name(s) (full paths, same defaults as categoryid)
`--websiteid` |        Website Id(s) (i.e. '1,2', default is all)
`--website` |          Website name(s) (i.e. 'base', same defaults as websiteid)
`--status` |           Status (0: disabled, 1: enabled - default)
`--image` |            Image filename relative to media/import (i.e. '/xyz/8.jpg')
`--imagesmall` |       Small Image filename (i.e. '/xyz/8.jpg')
`--imagethumb` | Thumbnail Image filename (i.e. '/xyz/8.jpg')
`--start`    |           Starting product number to count from (default 0)
`--padcount`  |          Amount of padding for {{.._pad}} macros (default 10)
`--randtext`  |          List of words to choose from randomly for {{randtext}}, or file containing list (defaults to random Lorem
`--randimages` |         List of images to choose from randomly, or file containing list
`--textlimit`   |        Sets limit for number of words used to fill {{randtext}} (default 5)
`--imagelimit`  |        Sets limit for number of images per product (default 3)
`--catlimit`    |        Sets limit for number of categories a product can be in (default 3)
`--sitelimit`   |        Sets limit for number of websites a product can be in (default 3)

### List categories ###

This works just like the Magerun built in `sys:website:list`, only for categories. During intial development we needed an easy way to get the corresponding category Ids for the various categories, and their various attributes (i.e. URL key and path).

        $ magerun sys:category:list
        
