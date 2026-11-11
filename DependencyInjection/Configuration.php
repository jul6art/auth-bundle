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
                ->arrayNode('sign_in_throttling')
                    ->info('A login limiter that counts the sign-in requests only: name "auth.sign_in_throttling.limiter" in the "login_throttling.limiter" of a firewall that also reads a token. Off by default; needs symfony/rate-limiter.')
                    ->canBeEnabled()
                    ->validate()
                        ->ifTrue(static fn (array $value): bool => true === $value['enabled'] && [] === $value['paths'])
                        ->thenInvalid('auth.sign_in_throttling needs at least one path: it would count nothing.')
                    ->end()
                    ->children()
                        ->arrayNode('paths')
                            ->info('The path prefixes where a request is a sign-in attempt, e.g. "/api/auth/" and "/login". Any other request of the firewall is neither counted nor refused.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->integerNode('max_attempts')
                            ->info('Failed attempts per identifier and IP within the interval; the IP itself gets five times as many, every identifier together.')
                            ->defaultValue(5)
                            ->min(1)
                        ->end()
                        ->scalarNode('interval')
                            ->info('The window the attempts are counted over.')
                            ->defaultValue('1 minute')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('cache_pool')
                            ->info('The cache pool of the counters. Null: a filesystem pool of the bundle ("auth.sign_in_throttling.cache"). Name your own to clear it between tests — never an array pool, which forgets at the end of each request.')
                            ->defaultNull()
                        ->end()
                    ->end()
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
