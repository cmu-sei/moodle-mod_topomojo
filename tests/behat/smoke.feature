@mod @mod_topomojo
Feature: TopoMojo pages that render without the TopoMojo API
  In order to trust the plugin's own pages and JavaScript
  As a teacher, a student and an administrator
  I need the lab directory, the course index, Manage Deployments and the plugin settings to work

  # The view page, and the activity form in the Dropdown and Searchable workspace selection
  # modes, call the TopoMojo API while they render and report the unconfigured API through
  # debugging(), so they are not covered here.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name      | intro                | course | idnumber |
      | topomojo | Alpha lab | First lab for Behat  | C1     | lab1     |
      | topomojo | Bravo lab | Second lab for Behat | C1     | lab2     |

  @javascript
  Scenario: The lab directory search filters the lab cards
    Given I am on the "mod_topomojo > Labs" page logged in as "student1"
    And I should see "Alpha lab"
    And I should see "Bravo lab"
    When I set the field with xpath "//input[contains(concat(' ', normalize-space(@class), ' '), ' lab-search ')]" to "alpha"
    Then I should see "Alpha lab"
    And I should not see "Bravo lab"
    And I should see "1 result for"
    And I click on ".lab-clear" "css_element"
    And I should see "Alpha lab"
    And I should see "Bravo lab"

  Scenario: The activity form in Manual mode saves the entered name and workspace id
    Given the following config values are set as admin:
      | autocomplete | 2 | topomojo |
    And I log in as "teacher1"
    And I add a "topomojo" activity to course "Course 1" section "1"
    And I should see "New TopoMojo"
    And "input[name='workspaceid'][type='text']" "css_element" should exist
    When I set the following fields to these values:
      | name        | Charlie lab        |
      | workspaceid | behat-workspace-01 |
    And I press "Save and return to course"
    Then I should see "Charlie lab"
    And I should not see "Unknown Lab"

  Scenario: The course index lists the TopoMojo activities
    When I am on the "C1" "mod_topomojo > Index" page logged in as "student1"
    Then I should see "Alpha lab"
    And I should see "Second lab for Behat"

  @javascript
  Scenario: Manage Deployments enables the bulk actions for the selected users
    Given I am on the "Alpha lab" "mod_topomojo > Manage" page logged in as "teacher1"
    And I should see "Manage Deployments"
    And the "Deploy Selected Now" "button" should be disabled
    When I click on ".user-checkbox" "css_element" in the "Sam Student" "table_row"
    Then the "Deploy Selected (1)" "button" should be enabled
    And the "Schedule Selected (1)..." "button" should be enabled
    And I click on "Deploy Selected (1)" "button"
    And I should see "Deploy gamespaces for selected users?" in the "Deploy Selected (1)" "dialogue"
    And I click on "Cancel" "button" in the "Deploy Selected (1)" "dialogue"
    And I click on "Deselect All" "button"
    And the "Deploy Selected Now" "button" should be disabled

  Scenario: The settings page rejects an API key without an external manager
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > TopoMojo" in site administration
    And I set the field "Enable External API Key" to "1"
    When I press "Save changes"
    Then I should see "Enable External Manager User must also be enabled when Enable External API Key is enabled."
    And I set the field "Enable External API Key" to "1"
    And I set the field "Enable External Manager User" to "1"
    And I set the field "Manager Name" to "behat-manager"
    And I press "Save changes"
    And I should see "Changes saved"
    And the field "Manager Name" matches value "behat-manager"
