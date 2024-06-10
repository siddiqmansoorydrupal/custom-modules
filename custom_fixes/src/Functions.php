<?php

namespace Drupal\custom_fixes;


class Functions
{
    public static function termBreadCrumb($term, $crumbs = [])
    {


        $termName = $term->getName();
        $taxonomy_alias = $term->get('field_taxonomy_alias')->getString();
		
		if(empty($taxonomy_alias)){
			 $taxonomy_alias = $term->get('field_default_alias')->getString();
		}
		
		if(empty($taxonomy_alias)){
			 $taxonomy_alias = $term->get('name')->getString();
		}
		
        $ternNameReplaced = strtolower(str_replace(' ', '-', $termName));
        $url = \Drupal\Core\Url::fromUserInput("/order-online/" . $ternNameReplaced)->toString();

        $crumbs[] = [
            'text' => $taxonomy_alias,
            'url' => $url,

        ];

        $parents = $term->parent->referencedEntities();
        if ($parents) {
            $crumbs = static::termBreadCrumb($parents[0], $crumbs);
        }
        return $crumbs;
    }
}
