@mod @mod_stage
Feature: Internship circuit from self-assessment to academic office validation
  In order to validate students' internships
  As a student, a referent teacher and the academic office
  I need to self-assess, evaluate and validate internships in turn

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

  Scenario: A student self-assesses an internship once its agreement is signed
    Given the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend     | declaredduration | conventionstatus |
      | Internships | student1 | Clinic | ##-10 days## | ##-3 days## | 6                | signed           |
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    Then I should see "My internships"
    And I should see "Clinic"
    When I follow "Self-assess my internship"
    And I set the field "Student self-assessment" to "I took part in the consultations every day."
    # The page also carries the "Save changes" button of the actual-days form: target the form's own submit button.
    And I click on "#id_submitbutton" "css_element"
    Then I should see "Internship saved."
    And I should see "Self-assessed by student"

  Scenario: The referent teacher evaluates a self-assessed internship
    Given the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend     | declaredduration | conventionstatus | status       | studentselfeval                   |
      | Internships | student1 | Clinic | ##-10 days## | ##-3 days## | 6                | signed           | selfassessed | I took part in the consultations. |
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher1
    Then I should see "Internships to evaluate"
    And I should see "Zoé Martin"
    When I follow "Evaluate"
    Then I should see "I took part in the consultations."
    When I set the field "Teacher evaluation" to "Serious and committed student."
    And I press "Validate"
    Then I should see "Evaluation saved."
    When I am on the "Internships" "mod_stage > Academic office validation" page logged in as deve1
    Then I should see "Zoé Martin"
    And I should see "Evaluated by teacher"

  Scenario: The referent teacher refuses to validate an internship and must give a reason
    Given the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend     | declaredduration | conventionstatus | status       |
      | Internships | student1 | Clinic | ##-10 days## | ##-3 days## | 6                | signed           | selfassessed |
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher1
    And I follow "Evaluate"
    And I press "Mark as not validated"
    Then I should not see "Evaluation saved."
    When I set the field "Reason for non-validation" to "The report is missing."
    And I press "Mark as not validated"
    Then I should see "Evaluation saved."
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    Then I should see "Not validated"

  Scenario: The academic office validates an internship with a retained duration
    Given the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend     | declaredduration | conventionstatus | status           | teachereval |
      | Internships | student1 | Clinic | ##-10 days## | ##-3 days## | 6                | signed           | teacherevaluated | Fine.       |
    When I am on the "Internships" "mod_stage > Academic office validation" page logged in as deve1
    Then I should see "Zoé Martin"
    When I follow "Validate"
    And I set the field "Retained duration (days)" to "5"
    And I set the field "Academic office comment" to "Validated after checking the report."
    And I press "Validate"
    Then I should see "Evaluation saved."
    When I am on the "Internships" "mod_stage > Pilotage" page
    Then I should see "Zoé Martin"
    When I follow "View details"
    Then I should see "Validated"
    And I should see "5"

  Scenario: The referent teacher validates an agreement request before it reaches the academic office
    Given the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend      | declaredduration | conventionstatus | conventiondetail |
      | Internships | student1 | Clinic | ##+10 days## | ##+20 days## | 8                | teacherpending   | complete         |
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher1
    Then I should see "Agreement requests to validate"
    And I should see "Zoé Martin"
    When I follow "Validate the request"
    Then I should see "Agreement request from Zoé Martin"
    When I press "Validate"
    Then I should see "The agreement request has been validated and sent to the DEVE."
    When I am on the "Internships" "mod_stage > Conventions" page logged in as deve1
    Then I should see "Zoé Martin"
    And I should see "Requested"
