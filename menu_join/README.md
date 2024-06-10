# Menu Join

This module allows us to use distinct menus as if they were submenus of a 
parent. 

_Why?_ You may find splitting larger menus into smaller submenus is easier to 
manage. You can also assign different permissions per menu.  

This module relies on the Context Active Trail 
https://www.drupal.org/project/context_active_trail module to handle the active 
trails. 

## Important!
Requires patch for bug in Context Active Trail module:
https://www.drupal.org/project/context_active_trail/issues/3268667

## Examples

### Example 1 - Creating breadcrumbs for a submenu
We have an `about` menu which we want to appear as a sub menu beneath the 
_About Us_ link in the `main` menu.

Steps:
* Create one context 
* Add the condition _In Menu Tree_ and select the `about` menu. 
* Add an _Active Trail_ reaction pointing to the _Our Services_ link in the 
`main` menu. 
* Add a _Menu Join_ reaction selecting _Parent Menu_.

### Example 2 - Adding nodes by type beneath a link in a submenu
We want events nodes to appear beneath the Events link in the  an `about` menu 
which in turn should appear as a sub menu beneath the _About Us_ link in the 
`main` menu.

Steps:
* Create 1st context
  * Add the condition Content Type = Event.
  * Add an _Active Trail_ reaction pointing to the _About Us_ link in the `main` 
menu.
  * Add a _Menu Join_ reaction selecting _Parent Menu_.
* Create 2nd context
    * Add the condition Content Type = Event.
    * Add an _Active Trail_ reaction pointing to the _Events_ link in the 
`about` menu.
    * Add a _Menu Join_ reaction selecting _main_ menu as the parent menu.
