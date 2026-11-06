<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('auth');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Exposed as the "auth.enabled" container parameter.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('api')
                    ->info('The API sign-in: password, second factor, cookies, refresh, forgotten password. Off by default; its packages are suggested, not required.')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('two_factor_required')
                            ->info('Whether every account owes a second factor. Ignored in the "prod" environment, where it always does.')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('realms')
                            ->info('One realm per application whose accounts sign in: its firewalls, its classes, its name, its e-mails.')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('firewalls')
                                        ->info('The firewalls of the realm: its sign-in, its public routes, its API.')
                                        ->isRequired()
                                        ->requiresAtLeastOneElement()
                                        ->scalarPrototype()->cannotBeEmpty()->end()
                                    ->end()
                                    ->scalarNode('user_class')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('login_record_class')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('refresh_token_class')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('reset_password_request_class')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('issuer')
                                        ->info('The name the authenticator app and the e-mails show.')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->arrayNode('mail')
                                        ->isRequired()
                                        ->children()
                                            ->scalarNode('from')->isRequired()->cannotBeEmpty()->end()
                                            ->arrayNode('reset_links')
                                                ->info('The reset screen by language, "{token}" in place of the token: never built from the request\'s host.')
                                                ->isRequired()
                                                ->requiresAtLeastOneElement()
                                                ->useAttributeAsKey('locale')
                                                ->scalarPrototype()->cannotBeEmpty()->end()
                                            ->end()
                                            ->scalarNode('html_template')->defaultValue('@Auth/emails/password_reset.html.twig')->end()
                                            ->scalarNode('text_template')->defaultValue('@Auth/emails/password_reset.txt.twig')->end()
                                            ->scalarNode('subject')->defaultValue('password_reset.subject')->end()
                                            ->scalarNode('translation_domain')->defaultValue('auth_emails')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
