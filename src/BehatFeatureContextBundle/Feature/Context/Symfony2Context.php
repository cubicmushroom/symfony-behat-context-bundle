<?php
/**
 * Created by PhpStorm.
 * User: toby
 * Date: 08/11/2015
 * Time: 16:35
 */

namespace CubicMushroom\Symfony\BehatFeatureContextBundle\Feature\Context;

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Context\Exception\ContextNotFoundException;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Symfony2Extension\Driver\KernelDriver;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Class Symfony2Context
 *
 * @package LIMTool\PaymentsBundle
 */
class Symfony2Context implements KernelAwareContext
{
    // -----------------------------------------------------------------------------------------------------------------
    // Properties
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var MinkContext
     */
    private $minkContext;


    // -----------------------------------------------------------------------------------------------------------------
    // Hooks
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @param BeforeScenarioScope $scope
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        /** @var InitializedContextEnvironment $environment */
        $environment = $scope->getEnvironment();

        if (!$environment->hasContextClass(MinkContext::class)) {
            throw new ContextNotFoundException('Mink context is not available', MinkContext::class);
        }
        $this->minkContext = $environment->getContext(MinkContext::class);
    }


    /**
     * @param BeforeScenarioScope $scope
     *
     * @BeforeScenario
     */
    public function reEnableRedirects()
    {
        $this->iAllowRedirects();
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Steps
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @When /^I enable the Symfony profiler$/
     */
    public function iEnableTheSymfonyProfiler()
    {
        $this->getClient()->enableProfiler();
    }


    /**
     * @When /^I disable redirects$/
     */
    public function iPreventRedirects()
    {
        $this->getClient()->followRedirects(false);
    }


    /**
     * @When /^I enable redirects$/
     * @When /^I allow redirects$/
     */
    public function iAllowRedirects()
    {
        $this->getClient()->followRedirects(true);
    }


    /**
     * @Given /^I follow redirect$/
     */
    public function iFollowRedirect()
    {
        $this->getClient()->followRedirect();
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @return Profile
     */
    public function getProfile()
    {
        return $this->getClient()->getProfile();
    }


    // -----------------------------------------------------------------------------------------------------------------
    // Getters and Setters
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Sets Kernel instance.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }


    /**
     * @return Client
     */
    protected function getClient()
    {
        $driver = $this->minkContext->getSession()->getDriver();

        if (!$driver instanceof KernelDriver) {
            throw new \RuntimeException('This step can only be used with the Symfony2Extension Kernel driver');
        }

        /** @var Client $client */
        $client = $driver->getClient();

        return $client;
    }
}