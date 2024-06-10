<?php

namespace Drupal\menu_join;

use Drupal\context\ContextManager;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Build breadcrumbs based on menu_join_active_trail from context.
 */
class MenuJoinBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The context manager.
   *
   * @var \Drupal\context\ContextManager
   */
  protected $contextManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityRepository
   */
  protected $entityRepository;

  /**
   * The active trail.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $activeTrail;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $linkManager;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Configuration of the context reaction affecting breadcrumbs.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Current menu name, used in iteration.
   *
   * @var string
   */
  protected $currentMenuName = '';

  /**
   * Our menu list.
   *
   * @var array
   */
  protected $menuList = [];

  /**
   * Constructor.
   *
   * @param \Drupal\context\ContextManager $context_manager
   *   The context manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityRepository $entity_repository
   *   Entity Repository.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $active_trail
   *   The active trail.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $link_manager
   *   The menu link manager.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    ContextManager $context_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepository $entity_repository,
    MenuActiveTrailInterface $active_trail,
    MenuLinkManagerInterface $link_manager,
    TitleResolverInterface $title_resolver,
    RequestStack $request_stack
  ) {
    $this->contextManager = $context_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->activeTrail = $active_trail;
    $this->linkManager = $link_manager;
    $this->titleResolver = $title_resolver;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return count($this->getActiveReactions()) > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveReactions() {
    return $this->contextManager->getActiveReactions('menu_join');
  }

  /**
   * Returns list of active trails keyed by menu name.
   *
   * @return array
   *   Array of active Trails.
   */
  public function getActiveTrails() {
    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();
    $activeTrails = [];
    foreach ($menus as $menu_name => $menu) {
      $at = $this->activeTrail->getActiveTrailIds($menu_name);
      $link_ids = array_filter($at);
      $trail_links = [];
      $trail_enabled = TRUE;
      if (!empty($link_ids)) {
        foreach ($link_ids as $link_id) {
          $menuLink = $this->linkFromLinkId($link_id);
          // Do not add any trail containing disabled items.
          if ($menuLink->isEnabled() !== TRUE) {
            $trail_enabled = FALSE;
            break;
          }
          $trail_links[$link_id] = $link = $this->linkManager->getInstance(['id' => $link_id]);
        }
        // Add only if not disabled.
        if ($trail_enabled) {
          $activeTrails[$menu_name] = $trail_links;
        }
      }
    }
    return $activeTrails;
  }

  /**
   * Extracts menu machine name from link id.
   *
   * @param string $id
   *   Link id.
   *
   * @return false|string
   *   Menu machine name.
   */
  protected function menuNameFromLinkId($id) {
    return substr($id, 0, strpos($id, ':'));
  }

  /**
   * Loads MenuLInkContent item from link id.
   *
   * @param string $linkId
   *   Link id.
   *
   * @return false|string
   *   Menu machine name.
   */
  protected function linkFromLinkId($linkId) {
    $linkIdSplit = explode(':', $linkId);
    $link = $this->entityRepository->loadEntityByUuid($linkIdSplit[0], $linkIdSplit[1]);
    return $link;
  }

  /**
   * Orders active menus.
   */
  protected function calculateMenuJoinList() {

    $this->menuList = [];
    $parent_menu = "";

    // Get the parent menu.
    $contexts = $this->contextManager->getActiveContexts();
    foreach (($contexts) as $context) {
      if ($context->hasReaction('menu_join')) {
        $menu_join = $context->getReaction('menu_join');
        $menu_join_conf = $menu_join->getConfiguration();
        // If no parent, we select first item. CHild menu is new parent.
        if ($parent_menu === "") {
          $this->menuList[] = $menu_join_conf['menu_0'];
          $this->menuList[] = $menu_join_conf['menu_1'];
          $parent_menu = $menu_join_conf['menu_1'];
        }
        // Add menu trail if parent matches previous menu join.
        elseif ($parent_menu === $menu_join_conf['menu_0']) {
          $this->menuList[] = $menu_join_conf['menu_1'];
          $parent_menu = $menu_join_conf['menu_1'];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['url.path']);

    // Start with home page.
    $breadcrumb->addLink(
      Link::createFromRoute($this->t('Home'), '<front>')
    );

    // Get an ordered list of menus based on menu_join.
    $this->calculateMenuJoinList();
    // Get active trails (includes all active trails).
    $active_trails = $this->getActiveTrails();

    $breadcrumbs = [];
    foreach ($this->menuList as $menu_name) {
      if (empty($active_trails[$menu_name])) {
        continue;
      }
      $links = array_reverse($active_trails[$menu_name]);
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
      if (!empty($links)) {
        foreach ($links as $link) {
          $urlObject = $link->getUrlObject();

          if ($urlObject->isRouted()) {
            $path = $urlObject->isExternal()
              ? $urlObject->getUri()
              : $urlObject->getInternalPath();

            // We use path as a key here to remove duplicate breadcrumbs.
            $breadcrumbs[$path] = $link;
          }
        }
      }
    }

    foreach ($breadcrumbs as $link) {
      $breadcrumb->addLink(
        Link::fromTextAndUrl($link->getTitle(), $link->getUrlObject())
      );
    }

    return $breadcrumb;
  }

}
