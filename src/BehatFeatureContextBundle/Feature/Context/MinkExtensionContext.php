<?php
/**
 * Created by PhpStorm.
 * User: toby
 * Date: 16/11/2015
 * Time: 17:45
 */

namespace CubicMushroom\Symfony\BehatFeatureContextBundle\Feature\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;
use PhpSpec\Exception\Example\FailureException;

/**
 * Class MinkExtensionContext
 *
 * @package LIMTool\KeyFITBundle\Features\Context
 */
class MinkExtensionContext implements Context
{
    /**
     * @var MinkContext
     */
    private $minkContext;


    /**
     * @param BeforeScenarioScope $scope
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        /** @var InitializedContextEnvironment $environment */
        $environment = $scope->getEnvironment();

        $this->minkContext = $environment->getContext(MinkContext::class);
    }


    /**
     * @When /^I(?: should)? see the following query parameters:$/
     *
     * @param TableNode $table
     *
     * @throws FailureException
     */
    public function iSeeTheFollowingQueryParameters(TableNode $table)
    {
        $expectedParams = $table->getRowsHash();

        $UriParts = parse_url($this->minkContext->getSession()->getCurrentUrl());
        parse_str($UriParts['query'], $actualParams);

        foreach ($expectedParams as $key => $expectedParam) {
            if (!isset($actualParams[$key])) {
                throw new FailureException("Parameter '{$key}' is missing from page parameters'");
            }
            if ($expectedParam !== $actualParams[$key]) {
                throw new FailureException(
                    sprintf(
                        "Parameter '%s' does not match.  Expected '%s' but got '%s'",
                        $key,
                        $expectedParam,
                        $actualParams[$key]
                    )
                );
            }
            unset($actualParams[$key]);
        }

        if (!empty($actualParams)) {
            throw new FailureException(
                sprintf('Extra parameters found… (%s)', implode(', ', array_keys($actualParams)))
            );
        }
    }


    /**
     * Click on the element with the provided CSS Selector
     *
     * @When /^I click on the element with css selector "([^"]*)"$/
     */
    public function iClickOnTheElementWithCSSSelector($cssSelector)
    {
        $session = $this->minkContext->getSession();
        $element = $session->getPage()->find(
            'xpath',
            $session->getSelectorsHandler()->selectorToXpath('css', $cssSelector) // just changed xpath to css
        );
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS Selector: "%s"', $cssSelector));
        }

        $element->click();
    }


    /**
     * @When /^(?:|I )fill in hidden "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
     */
    public function iFillInHiddenFieldWith($field, $value)
    {
        $field = $this->fixStepArgument($field);
        $value = $this->fixStepArgument($value);

        $session = $this->minkContext->getSession();
        $page    = $session->getPage();
        $field   = $page->find('css', 'input[name="'.$field.'"]');

        if (empty($field)) {
            throw new \ExpectedException('Unable to find field');
        }

        $field->setValue($value);
    }


    /**
     * @Given /^I wait "(\d+)" seconds$/
     *
     * @param int $seconds
     */
    public function iWaitSeconds($seconds)
    {
        $this->minkContext->getSession()->wait($seconds * 1000);
    }


    /**
     * @Given /^I wait for the page to change after pressing "(?P<button>[^"]+)"$/
     *
     * @param $button
     *
     * @return bool
     */
    public function iWaitForThePageToChange($button)
    {
        $maxSeconds = 10;
        $session = $this->minkContext->getSession();
        $startingUrl = $session->getCurrentUrl();

        $button = $this->fixStepArgument($button);
        $session->getPage()->pressButton($button);

        $start = microtime(true);
        $end   = $start + ($maxSeconds * 1000) / 1000.0;

        while ((microtime(true) < $end && $session->getCurrentUrl() === $startingUrl)) {
            usleep(100000);
        }

        return (bool)$session->getCurrentUrl() !== $startingUrl;
    }


    /**
     * Returns fixed step argument (with \\" replaced back to ").
     *
     * @param string $argument
     *
     * @return string
     */
    protected function fixStepArgument($argument)
    {
        return str_replace('\\"', '"', $argument);
    }
}