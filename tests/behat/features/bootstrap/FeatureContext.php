<?php

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext {

  /**
   * The mink context.
   *
   * @var Drupal\DrupalExtension\Context\MinkContext
   */
  protected $minkContext;

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
  }

  /**
   * Gives us acess to the other contexts so we can access their properties.
   *
   * @BeforeScenario
   */
  public function before(BeforeScenarioScope $scope) {
    $environment = $scope->getEnvironment();

    $this->minkContext = $environment->getContext('Drupal\DrupalExtension\Context\MinkContext');
  }

  /**
   * Fill in wysiwyg on field.
   *
   * @Then I fill in wysiwyg on field :locator with :value
   */
  public function iFillInWysiwygOnFieldWith($locator, $value) {
    $el = $this->getSession()->getPage()->findField($locator);
    if (empty($el)) {
      throw new ExpectationException('Could not find WYSIWYG with locator: ' . $locator, $this->getSession());
    }
    $fieldId = $el->getAttribute('id');
    if (empty($fieldId)) {
      throw new Exception('Could not find an id for field with locator: ' . $locator);
    }
    $this->getSession()
      ->executeScript("CKEDITOR.instances[\"$fieldId\"].setData(\"$value\");");
  }

  /**
   * Wait for the page load.
   *
   * @Given /^I wait for the page to load$/
   */
  public function iWaitForThePageToLoad() {
    $this->getSession()->wait(50000, '(0 === jQuery.active)');
  }

  /**
   * Clicks on a tab with the specified text in active entity browser.
   *
   * @param string $tab
   *   The text of the tab to switch to.
   *
   * @When I switch to the :tab Entity Browser tab
   */
  public function switchToEntityBrowserTab($tab) {
    $this
      ->assertSession()
      ->elementExists('css', 'nav.eb-tabs')
      ->clickLink($tab);

    // I don't see any way to assert the tab specifically has loaded. So,
    // instead we just wait a reasonable amount of time.
    sleep(5);
  }

  /**
   * Switches out of an frame, into the main window.
   *
   * @When I switch to main window
   */
  public function exitfromFrame() {
    $this->getSession()->getDriver()->switchToIFrame();
  }

  /**
   * Fill in a Drupal autocomplete field.
   *
   * @When I fill in the autocomplete :field with :text and click :popup
   *
   * Example: I fill in the autocomplete 'field_tags_taxo[0][target_id]' with
   * 'Tag 1' and click 'Tag 1'
   */
  public function iFillAutocompleteField($field, $text, $popup) {
    $session = $this->getSession();
    $element = $session->getPage()->findField($field);
    if (empty($element)) {
      throw new ElementNotFoundException($session, NULL, 'named', $field);
    }

    $element->setValue($text);
    $element->focus();

    $xpath = $element->getXpath();
    $driver = $session->getDriver();
    // autocomplete.js uses key down/up events directly.
    // Press the down arrow to open the autocomplete options.
    $driver->keyDown($xpath, 40);
    $driver->keyUp($xpath, 40);

    $this->minkContext->iWaitForAjaxToFinish();
    $available_autocompletes = $this->getSession()->getPage()->findAll('css', 'ul.ui-autocomplete[id^=ui-id]');
    if (empty($available_autocompletes)) {
      throw new \Exception(t('Could not find the autocomplete popup box'));
    }

    // It's possible for multiple autocompletes to be on the page at once,
    // but it shouldn't be possible for multiple to be visible/open at once.
    foreach ($available_autocompletes as $autocomplete) {
      if ($autocomplete->isVisible()) {
        $matched_element = $autocomplete->find('xpath', "//a[text()='${popup}']");

        // If element was not found inside 'a' look inside 'span//span`.
        if (NULL === $matched_element) {
          $matched_element = $autocomplete->find('xpath', "//a//span//span[text()='${popup}']");
        }

        if (NULL === $matched_element) {
          throw new \Exception(t('Could not find autocomplete popup text @popup', [
            '@popup' => $popup,
          ]));
        }

        $matched_element->click();
        $this->minkContext->iWaitForAjaxToFinish();

      }
    }
  }

}
