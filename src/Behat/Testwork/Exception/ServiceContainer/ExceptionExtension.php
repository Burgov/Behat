<?php

/*
 * This file is part of the Behat Testwork.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Testwork\Exception\ServiceContainer;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ServiceProcessor;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Testwork autoloader extension.
 *
 * Provides exception handling services.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class ExceptionExtension implements Extension
{
    /*
     * Available services
     */
    const PRESENTER_ID = 'exception.presenter';

    /*
     * Available extension points
     */
    const STRINGER_TAG = 'exception.stringer';

    /**
     * @var ServiceProcessor
     */
    private $processor;

    /**
     * Initializes extension.
     *
     * @param null|ServiceProcessor $processor
     */
    public function __construct(ServiceProcessor $processor = null)
    {
        $this->processor = $processor ? : new ServiceProcessor();
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        return 'exception';
    }

    /**
     * Setups configuration for the extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder)
    {
    }

    /**
     * Loads extension services into temporary container.
     *
     * @param ContainerBuilder $container
     * @param array            $config
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $this->loadPresenter($container);
        $this->loadDefaultStringers($container);
    }

    /**
     * Processes shared container after all extensions loaded.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->processStringers($container);
    }

    /**
     * Loads exception presenter.
     *
     * @param ContainerBuilder $container
     */
    protected function loadPresenter(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Testwork\Exception\ExceptionPresenter', array('%paths.base%'));
        $container->setDefinition(self::PRESENTER_ID, $definition);
    }

    /**
     * Loads default stringer.
     *
     * @param ContainerBuilder $container
     */
    protected function loadDefaultStringers(ContainerBuilder $container)
    {
        $definition = new Definition('Behat\Testwork\Exception\Stringer\PHPUnitExceptionStringer');
        $definition->addTag(self::STRINGER_TAG, array('priority' => 50));
        $container->setDefinition(self::STRINGER_TAG . '.phpunit', $definition);
    }

    /**
     * Processes all available exception stringers.
     *
     * @param ContainerBuilder $container
     */
    protected function processStringers(ContainerBuilder $container)
    {
        $references = $this->processor->findAndSortTaggedServices($container, self::STRINGER_TAG);
        $definition = $container->getDefinition(self::PRESENTER_ID);

        foreach ($references as $reference) {
            $definition->addMethodCall('registerExceptionStringer', array($reference));
        }
    }
}
