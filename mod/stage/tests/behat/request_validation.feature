@mod @mod_stage
Feature: Internship request questionnaire and validation by the teacher in charge of the theme
  In order to agree to an internship before the academic office prepares its agreement
  As a teacher in charge of a theme or a referent teacher
  I need to validate the request on the basis of the student's questionnaire and agreement information

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Zoé       | Martin   | student1@example.com |
      | teacher1 | Luc       | Bernard  | teacher1@example.com |
      | teacher2 | Inès      | Moreau   | teacher2@example.com |
      | deve1    | Anne      | Durand   | deve1@example.com    |
    And the following "courses" exist:
      | fullname       | shortname |
      | Promotion 2026 | P2026     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | P2026  | student        |
      | teacher1 | P2026  | teacher        |
      | teacher2 | P2026  | teacher        |
      | deve1    | P2026  | editingteacher |

  Scenario: The student answers the theme questionnaire with the request and the teacher in charge validates it
    Given the following "activities" exist:
      | activity | course | name        | idnumber | conventionrequireteachervalidation | conventionvalidator |
      | stage    | P2026  | Internships | stage1   | 1                                  | themeteacher        |
    And the following "mod_stage > themes" exist:
      | stage       | name   |
      | Internships | Clinic |
    And the following "mod_stage > teacher assignments" exist:
      | stage       | student  | teacher  |
      | Internships | student1 | teacher1 |
    And the following "mod_stage > theme teachers" exist:
      | stage       | theme  | teacher  |
      | Internships | Clinic | teacher2 |
    And the following "mod_stage > convention templates" exist:
      | stage       | name              | lang |
      | Internships | Standard template | fr   |
    And the following "mod_stage > evaluation questions" exist:
      | stage       | list         | evaltype | name                            | required | theme  |
      | Internships | Request list | request  | Why did you choose this clinic? | 1        | Clinic |
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    And I follow "Request an agreement (outside StageVet)"
    Then I should see "Internship request questionnaire"
    And I set the following fields to these values:
      | structure                 | Parc veterinary clinic  |
      | declaredduration          | 10                      |
      | perioddatestart[0][day]   | 2                       |
      | perioddatestart[0][month] | March                   |
      | perioddatestart[0][year]  | ##+1 year##%Y##         |
      | perioddateend[0][day]     | 13                      |
      | perioddateend[0][month]   | March                   |
      | perioddateend[0][year]    | ##+1 year##%Y##         |
      | studentaddress            | 12 rue des Lilas, Lyon  |
      | studentphone              | 0600000001              |
      | hostaddress               | 4 place du Marché, Lyon |
      | hostrepresentative        | Dr Paul Roux            |
      | hostrepresentativetitle   | Director                |
      | hostservice               | Small animals           |
      | hostphone                 | 0400000002              |
      | hostemail                 | clinic@example.com      |
      | siret                     | 12345678901234          |
      | tutorname                 | Dr Marie Petit          |
      | tutorfunction             | Veterinarian            |
      | tutorphone                | 0400000003              |
      | tutoremail                | tutor@example.com       |
      | gratificationamount       | 600                     |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "This SIRET number is not valid"
    And I should see "An answer is required for this question: Why did you choose this clinic?"
    When I set the following fields to these values:
      | siret                           | 732 829 320 00074                        |
      | Why did you choose this clinic? | Its emergency service treats exotic pets. |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "The agreement request is waiting for your teachers' validation before reaching the DEVE."
    # The referent is not asked: the activity only requires the teacher in charge of the theme.
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher1
    Then I should see "No agreement requests awaiting your validation."
    # The teacher in charge of the theme has no assigned student but sees the request.
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher2
    Then I should see "Agreement requests to validate"
    And I should see "Zoé Martin"
    When I follow "Validate the request"
    Then I should see "Why did you choose this clinic?"
    And I should see "Its emergency service treats exotic pets."
    And the field "siret" matches value "73282932000074"
    When I press "Validate"
    Then I should see "The agreement request has been validated and sent to the DEVE."
    When I am on the "Internships" "mod_stage > Conventions" page logged in as deve1
    Then I should see "Zoé Martin"
    And I should see "Requested"

  Scenario: When both teachers must validate, the request reaches the academic office after the second validation
    Given the following "activities" exist:
      | activity | course | name        | idnumber | conventionrequireteachervalidation | conventionvalidator |
      | stage    | P2026  | Internships | stage1   | 1                                  | both                |
    And the following "mod_stage > themes" exist:
      | stage       | name   |
      | Internships | Clinic |
    And the following "mod_stage > teacher assignments" exist:
      | stage       | student  | teacher  |
      | Internships | student1 | teacher1 |
    And the following "mod_stage > theme teachers" exist:
      | stage       | theme  | teacher  |
      | Internships | Clinic | teacher2 |
    And the following "mod_stage > entries" exist:
      | stage       | user     | theme  | datestart    | dateend      | declaredduration | conventionstatus | conventiondetail |
      | Internships | student1 | Clinic | ##+10 days## | ##+20 days## | 8                | teacherpending   | complete         |
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher1
    And I follow "Validate the request"
    Then I should see "Teacher in charge of the theme: awaited"
    When I press "Validate"
    Then I should see "Your validation has been recorded. The request is waiting for the other validation"
    And I should see "No agreement requests awaiting your validation."
    When I am on the "Internships" "mod_stage > Teacher validation" page logged in as teacher2
    And I follow "Validate the request"
    Then I should see "Referent teacher: validated by Luc Bernard"
    When I press "Validate"
    Then I should see "The agreement request has been validated and sent to the DEVE."
    When I am on the "Internships" "mod_stage > Conventions" page logged in as deve1
    Then I should see "Zoé Martin"
    And I should see "Requested"
