@mod @mod_stage
Feature: Rated questions in evaluation lists, and resetting an internship
  In order to rate internships on a scale and correct a validation
  As the academic office, a student and a referent teacher
  I need questions rated from 1 to 5, and a reset that cancels the academic office's decision

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Zoé       | Martin   | student1@example.com |
      | teacher1 | Luc       | Bernard  | teacher1@example.com |
      | deve1    | Anne      | Durand   | deve1@example.com    |
    And the following "courses" exist:
      | fullname       | shortname |
      | Promotion 2026 | P2026     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | P2026  | student        |
      | teacher1 | P2026  | teacher        |
      | deve1    | P2026  | editingteacher |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | stage    | P2026  | Internships | stage1   |
    And the following "mod_stage > themes" exist:
      | stage       | name   |
      | Internships | Clinic |
    And the following "mod_stage > teacher assignments" exist:
      | stage       | student  | teacher  |
      | Internships | student1 | teacher1 |

  Scenario: The academic office adds a rated question to an evaluation list
    Given I am on the "Internships" "mod_stage > Evaluation lists" page logged in as deve1
    When I follow "Create an evaluation list"
    And I set the following fields to these values:
      | List name             | Self-assessment         |
      | Applies to form       | Student self-assessment |
      | questionqtype[0]      | Rating (1 to 5)         |
      | questionname[0]       | Welcome and supervision |
    And I press "Save changes"
    Then I should see "Evaluation list saved."
    # The page is reloaded from the database after saving.
    And the field "questionqtype[0]" matches value "Rating (1 to 5)"
    And the field "questionname[0]" matches value "Welcome and supervision"

  Scenario: A student rates a question, the referent teacher sees the rating
    Given the following "mod_stage > evaluation questions" exist:
      | stage       | list            | evaltype | theme  | qtype  | name                    | required |
      | Internships | Self-assessment | student  | Clinic | rating | Welcome and supervision | 1        |
    And the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend     | declaredduration | conventionstatus |
      | Internships | student1 | Clinic | ##-10 days## | ##-3 days## | 6                | signed           |
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    And I follow "Self-assess my internship"
    Then I should see "Welcome and supervision"
    And I should see "1 = very poor, 5 = excellent"
    # The list replaces the free self-assessment text.
    When I set the field with xpath "//input[starts-with(@name, 'q_')][@value='4']" to "1"
    # The page also carries the "Save changes" button of the actual-days form: target the questions' form.
    And I click on "//form[.//input[starts-with(@name, 'q_')]]//input[@type='submit']" "xpath_element"
    Then I should see "Internship saved."
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher1
    And I follow "Evaluate"
    Then I should see "Welcome and supervision"
    And I should see "4/5"

  Scenario: Resetting a validated internship cancels the academic office's validation
    Given the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend     | declaredduration | conventionstatus | status           | teachereval |
      | Internships | student1 | Clinic | ##-10 days## | ##-3 days## | 6                | signed           | teacherevaluated | Fine.       |
    And I am on the "Internships" "mod_stage > Academic office validation" page logged in as deve1
    And I follow "Validate"
    And I set the field "Retained duration (days)" to "5"
    And I set the field "Academic office comment" to "Validated after checking the report."
    And I press "Validate"
    And I am on the "Internships" "mod_stage > Register" page
    When I follow "Reset (allow a new submission)"
    Then I should see "The entry has been reset"
    When I am on the "Internships" "mod_stage > Pilotage" page
    # Student's summary, then the internship's own detail page.
    And I follow "View details"
    And I follow "View details"
    Then I should see "The internship"
    And I should see "Registered"
    And I should not see "Retained duration (days)"
    And I should not see "Academic office comment"
    And I should not see "Validated after checking the report."
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    Then I should see "Self-assess my internship"
