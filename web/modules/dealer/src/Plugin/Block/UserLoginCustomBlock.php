<?php

namespace Drupal\dealer\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'User login Custom' block.
 *
 * @Block(
 *   id = "user_login_custom_block",
 *   admin_label = @Translation("User login Custom"),
 *   category = @Translation("Forms")
 * )
 */
class UserLoginCustomBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use UrlGeneratorTrait;
  use RedirectDestinationTrait;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new UserLoginBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }


  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $route_name = $this->routeMatch->getRouteName();
    if ($account->isAnonymous() && !in_array($route_name, ['user.login', 'user.logout'])) {
      return AccessResult::allowed()
        ->addCacheContexts(['route.name', 'user.roles:anonymous']);
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\user\Form\UserLoginForm');
  	$form["#attributes"]["class"] = "user-login-custom-form";
    unset($form['name']['#attributes']['autofocus']);
    // When unsetting field descriptions, also unset aria-describedby attributes
    // to avoid introducing an accessibility bug.
    // @todo Do this automatically in https://www.drupal.org/node/2547063.
    unset($form['name']['#description']);
    unset($form['name']['#attributes']['aria-describedby']);
    unset($form['pass']['#description']);
    unset($form['pass']['#attributes']['aria-describedby']);
    $form['name']['#size'] = 15;
    //$form['name']['#title'] = t("N° Dossier");
    $form['pass']['#size'] = 15;
  	//$form['pass']['#title'] = t("CIN");
  	$form['actions']['submit']['#value'] = $this->t('Se connecter');
    $form['#action'] = $this->url('<current>', [], ['query' => $this->getDestinationArray(), 'external' => FALSE]);
    // Build action links.
    $items = [];
    return [
      'user_login_form' => $form,
      'user_links' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

}
