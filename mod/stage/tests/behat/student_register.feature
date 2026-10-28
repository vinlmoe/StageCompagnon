@mod @mod_stage
Feature: Students register an internship and request its agreement in one go
  In order to get an internship agreement without waiting for the academic office
  As a student
  I need to register the internship and fill in the agreement request on the same page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Zoé       | Martin   | student1@example.com |
      | teacher1 | Luc       | Bernard  | teacher1@example.com |
    And the following "courses" exist:
      | fullname       | shortname |
      | Promotion 2026 | P2026     |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | P2026  | student |
      | teacher1 | P2026  | teacher |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | stage    | P2026  | Internships | stage1   |
    And the following "mod_stage > themes" exist:
      | stage       | name   |
      | Internships | Clinic |
    And the following "mod_stage > teacher assignments" exist:
      | stage       | student  | teacher  |
      | Internships | student1 | teacher1 |
    And the following "mod_stage > convention templates" exist:
      | stage       | name              | lang |
      | Internships | Standard template | fr   |

  Scenario: An allowance too long for the database is refused by the form, without registering anything
    Given I am on the "Internships" "mod_stage > Activity" page logged in as student1
    When I follow "Request an agreement (outside StageVet)"
    And I set the following fields to these values:
      | structure                 | Parc veterinary clinic               |
      | declaredduration          | 10                                   |
      | perioddatestart[0][day]   | 2                                    |
      | perioddatestart[0][month] | March                                |
      | perioddatestart[0][year]  | ##+1 year##%Y##                      |
      | perioddateend[0][day]     | 13                                   |
      | perioddateend[0][month]   | March                                |
      | perioddateend[0][year]    | ##+1 year##%Y##                      |
      | studentaddress            | 12 rue des Lilas, Lyon               |
      | studentphone              | 0600000001                           |
      | hostaddress               | 4 place du Marché, Lyon              |
      | hostrepresentative        | Dr Paul Roux                         |
      | hostrepresentativetitle   | Director                             |
      | hostservice               | Small animals                        |
      | hostphone                 | 0400000002                           |
      | hostemail                 | clinic@example.com                   |
      | tutorname                 | Dr Marie Petit                       |
      | tutorfunction             | Veterinarian                         |
      | tutorphone                | 0400000003                           |
      | tutoremail                | tutor@example.com                    |
      | gratificationamount       | 600 euros per month, paid at the end |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "Maximum of 20 characters"
    And I should not see "The internship has been registered"
    When I am on the "Internships" "mod_stage > Activity" page
    Then I should not see "Parc veterinary clinic"
    # Same form, corrected: the internship and its agreement request are saved together.
    When I follow "Request an agreement (outside StageVet)"
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
      | tutorname                 | Dr Marie Petit          |
      | tutorfunction             | Veterinarian            |
      | tutorphone                | 0400000003              |
      | tutoremail                | tutor@example.com       |
      | gratificationamount       | 600                     |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "The internship has been registered and the agreement request sent to the DEVE."
    And I should see "Parc veterinary clinic"
