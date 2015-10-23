## UNL PHP DWT Parser Changelog

### 0.9.0

* Add support for immedaitely rendering a scanned DWT [saltybeagle]
* Prevent greedy matching of template regions [spam38]

### 0.8.0

* Dreamweaver templates using params are now properly supported
* Add `UNL_DWT::getTemplateFile()` which is used during the rendering process

### 0.7.2

* Scanner fix - do not replace newlines with spaces in scanned content

### 0.7.1

 * Declare debug method correctly as static.

### 0.7.0

* Move region class into separate file.
* Add scanner for simply scanning a dwt for regions, this does not replace the generator, but supplements it.

### 0.6.1

* Change is_a() to instanceof to fix warning.

### 0.6.0

* Move code around. `DWT.php` is now in `UNL/DWT.php` instead of `UNL/DWT/DWT.php` = not compatible with old versions of UNL_Templates!
* Switch to using static properties
* Upgrade a lot of code to PHP 5
* Add phpdoc headers and coding standards fixes
* Switch to BSD license

### 0.5.1

* Fix Bug #17: Empty region replacement erases template content.

### 0.5.0

* Fix Bug #16: Locked regions aren't detected correctly.
* Fix Bug #1: Include path modified incorrectly.

### 0.1.2

* Added missing setOption function. Initially only debug option is available.
* Renamed internally used function between to UNL_DWT_between
* created externally callable replaceRegions function.
* debug function for outputting messages levels 0-5.

### 0.1.1

* Added generator options `generator_include`/`exclude_regex` and `extends` and `extends_location`.
* Create `dwt_location` and `tpl_location` if it does not exist yet.
* Remove editable region tags for locked regions.

### 0.1.0

* First release, only basic functionality.