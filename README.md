# Silverstripe SortWeight Module

## Maintainer Contact 
 * Brice Burgess (Nickname: briceburg, brice, briceburgess)
   <nesta (at) iceburg (dot) net>
	
## Requirements
 * SilverStripe 2.4.x

## Overview
Allows easy ordering of objects in has-many and many-many relationships. 

Version 0.1 (beta)


### Features

 * Drag and Drop support through the CMS
 
 * Does not require DataObjectManager
 
 * Supports ordering of components in has-many and many-many relationship getters [ e.g. $set = $Artist->Albums(); will return properly ordered Albums ]

 * Unobtrusively drops into Silverstripe -- automatically extends ComplexTableField and _DataObjectManger (coming)_
 
 
### Demonstration

  * [YouTube video ID: NQC_L71EuW0](http://www.youtube.com/watch?v=NQC_L71EuW0)
  
	
### Configuration & Usage

 * Coming soon. See _config.php for an example.

### Known Issues
 
 * See TODO.md
	