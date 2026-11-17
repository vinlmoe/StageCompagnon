@mod @mod_stage
Feature: Themes with organisations offered by the institution
  In order to place a whole cohort in the host organisations chosen by the institution
  As a student and as the academic office
  I need to rank wishes, be matched to an organisation and request the agreement from that assignment

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Zoé       | Martin   | student1@example.com |
      | student2 | Hugo      | Roux     | student2@example.com |
      | teacher1 | Luc       | Bernard  | teacher1@example.com |
      | deve1    | Claire    | Dupont   | deve1@example.com    |
    And the following "courses" exist:
      | fullname       | shortname |
      | Promotion 2026 | P2026     |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | P2026  | student |
      | student2 | P2026  | student |
      | teacher1 | P2026  | teacher |
      | deve1    | P2026  | manager |
    And the following "activities" exist:
      | activity | course | name        | idnumber |
      | stage    | P2026  | Internships | stage1   |
    And the following "mod_stage > teacher assignments" exist:
      | stage       | student  | teacher  |
      | Internships | student1 | teacher1 |
      | Internships | student2 | teacher1 |
    And the following "mod_stage > convention templates" exist:
      | stage       | name              | lang |
      | Internships | Standard template | fr   |

  Scenario: Students rank their wishes, are matched, and the assigned student requests a prefilled agreement
    Given the following "mod_stage > themes" exist:
      | stage       | name  | placementenabled | placementchoices | placementdeadline |
      | Internships | Rural | 1                | 2                | +1 week           |
    And the following "mod_stage > placement offers" exist:
      | stage       | theme | name          | places | address               | representative | representativetitle | service | phone      | email            | tutorname   | tutorfunction | tutorphone | tutoremail        |
      | Internships | Rural | Alpine Farm   | 1      | 3 route des Alpes     | Mrs Anne Vial  | Manager             | Cattle  | 0400000010 | farm@example.com | Dr Paul Roy | Veterinarian  | 0400000011 | roy@example.com   |
      | Internships | Rural | Valley Clinic | 1      | 8 chemin de la Vallée | Mr Jean Blanc  | Director            | Mixed   | 0400000020 | val@example.com  | Dr Eve Noir | Veterinarian  | 0400000021 | noir@example.com  |
    # A student ranks wishes: the same organisation twice is refused, a valid ranking is recorded.
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    Then I should see "Offered organisations"
    And I should see "You have not recorded any wishes yet."
    When I follow "Rank my wishes"
    And I set the field "Wish no. 1" to "Alpine Farm — 3 route des Alpes"
    And I set the field "Wish no. 2" to "Alpine Farm — 3 route des Alpes"
    And I press "Save my wishes"
    Then I should see "Each organisation may appear only once in your wishes."
    When I set the field "Wish no. 1" to "Alpine Farm — 3 route des Alpes"
    And I set the field "Wish no. 2" to "Valley Clinic — 8 chemin de la Vallée"
    And I press "Save my wishes"
    Then I should see "Your wishes are recorded."
    And I log out
    # The other student ranks the opposite order: whatever the draw, each gets their first wish.
    Given the following "mod_stage > placement wishes" exist:
      | stage       | theme | user     | offers                     |
      | Internships | Rural | student2 | Valley Clinic, Alpine Farm |
    # The academic office runs the matching without waiting for the closing date.
    When I am on the "Internships > Rural" "mod_stage > Placement matching" page logged in as deve1
    Then I should see "2 student(s) have wishes and no assignment yet."
    When I press "Run the matching now"
    Then I should see "Matching done: 2 student(s) assigned, 0 without assignment."
    And I log out
    # Wishes are closed; the assigned student opens the prefilled request.
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    Then I should see "You are assigned to: Alpine Farm (Wish no. 1)."
    When I follow "Complete my agreement request"
    Then I should see "Your request is prefilled with the details of the organisation you are assigned to: Alpine Farm."
    And the field "structure" matches value "Alpine Farm"
    And the field "hostaddress" matches value "3 route des Alpes"
    And the field "tutoremail" matches value "roy@example.com"
    # The request must be for the assigned organisation.
    When I set the following fields to these values:
      | structure                 | Another Farm           |
      | declaredduration          | 10                     |
      | perioddatestart[0][day]   | 2                      |
      | perioddatestart[0][month] | March                  |
      | perioddatestart[0][year]  | ##+1 year##%Y##        |
      | perioddateend[0][day]     | 13                     |
      | perioddateend[0][month]   | March                  |
      | perioddateend[0][year]    | ##+1 year##%Y##        |
      | studentaddress            | 12 rue des Lilas, Lyon |
      | studentphone              | 0600000001             |
      | gratificationamount       | 0                      |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "it must be for the organisation assigned to you"
    When I set the field "structure" to "Alpine Farm"
    And I press "Request an agreement (outside StageVet)"
    Then I should see "The internship has been registered and the agreement request sent to the DEVE."
    And I should see "Agreement request submitted."
    And I should not see "Complete my agreement request"
    And I log out
    # Once the request is submitted, the academic office can no longer move the student.
    When I am on the "Internships > Rural" "mod_stage > Placement matching" page logged in as deve1
    And I set the field "Organisation assigned to Zoé Martin" to "No assignment"
    And I press "Save assignments"
    Then I should see "No assignment was changed."
    And I should see "cancel the internship first"

  Scenario: Outside the offered organisations, a request is refused unless the theme allows it
    Given the following "mod_stage > themes" exist:
      | stage       | name  | placementenabled | placementchoices |
      | Internships | Rural | 1                | 1                |
    And the following "mod_stage > placement offers" exist:
      | stage       | theme | name        |
      | Internships | Rural | Alpine Farm |
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    And I follow "Request an agreement (outside StageVet)"
    And I set the following fields to these values:
      | structure                 | Hill Farm               |
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
      | hostservice               | Cattle                  |
      | hostphone                 | 0400000002              |
      | hostemail                 | hill@example.com        |
      | siret                     | 732 829 320 00074       |
      | tutorname                 | Dr Marie Petit          |
      | tutorfunction             | Veterinarian            |
      | tutorphone                | 0400000003              |
      | tutoremail                | tutor@example.com       |
      | gratificationamount       | 0                       |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "This theme goes through the organisations offered by the institution"
    And I should not see "The internship has been registered"

  Scenario: When the theme allows it, a request outside the list needs the SIRET number, or a country abroad
    Given the following "mod_stage > themes" exist:
      | stage       | name  | placementenabled | placementchoices | placementofflist |
      | Internships | Rural | 1                | 1                | 1                |
    And the following "mod_stage > placement offers" exist:
      | stage       | theme | name        |
      | Internships | Rural | Alpine Farm |
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    Then I should see "You may also request an agreement for this theme with an organisation outside this list"
    When I follow "Request an agreement (outside StageVet)"
    And I set the following fields to these values:
      | structure                 | Hill Farm               |
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
      | hostservice               | Cattle                  |
      | hostphone                 | 0400000002              |
      | hostemail                 | hill@example.com        |
      | tutorname                 | Dr Marie Petit          |
      | tutorfunction             | Veterinarian            |
      | tutorphone                | 0400000003              |
      | tutoremail                | tutor@example.com       |
      | gratificationamount       | 0                       |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "The organisation's SIRET number is required for a request outside the offered organisations"
    # Ticking "abroad" without a country does not lift the requirement.
    When I set the field "abroad" to "1"
    And I press "Request an agreement (outside StageVet)"
    Then I should see "Enter the country of the internship (outside France)"
    And I should not see "The internship has been registered"
    When I set the field "abroad" to "0"
    And I set the field "siret" to "732 829 320 00074"
    And I press "Request an agreement (outside StageVet)"
    Then I should see "The internship has been registered and the agreement request sent to the DEVE."
    And I should see "Hill Farm"

  Scenario: The academic office builds the list of organisations and assigns a student by hand
    Given the following "mod_stage > themes" exist:
      | stage       | name   | placementenabled | placementchoices |
      | Internships | Rural  | 1                | 1                |
      | Internships | Equine | 0                | 1                |
    And the following "mod_stage > entries" exist:
      | stage       | user     | theme  | structure     | datestart  | dateend    |
      | Internships | student2 | Equine | Meadow Stable | 2026-03-02 | 2026-03-13 |
    When I am on the "Internships > Rural" "mod_stage > Placement offers" page logged in as deve1
    Then I should see "No organisation is offered for this theme yet."
    # By hand: a name made of spaces or fewer than one place is refused.
    When I follow "Add an organisation"
    And I set the following fields to these values:
      | name   | Alpine Farm |
      | places | 0           |
    And I press "Save changes"
    Then I should see "Enter at least one place."
    When I set the field "places" to "1"
    And I press "Save changes"
    Then I should see "Organisation saved."
    And I should see "Alpine Farm"
    # From previous internships of the activity.
    When I follow "Pick from previous internships"
    Then I should see "Meadow Stable"
    When I set the field "Meadow Stable" to "1"
    And I press "Add the ticked organisations"
    Then I should see "1 organisation(s) added, 0 already offered."
    And I should see "2 organisation(s) offered, 2 place(s) in total."
    # A student without wishes is assigned from the form at the top of the matching page.
    When I follow "Wishes and matching"
    And I set the field "Assign outside wishes" to "Zoé Martin"
    And I set the field "offerid" to "Alpine Farm (1 of 1 place(s) free)"
    And I press "Assign"
    Then I should see "1 assignment(s) changed."
    # The organisation now has a student: it can no longer be deleted.
    When I am on the "Internships > Rural" "mod_stage > Placement offers" page
    And I press "Delete"
    Then I should see "This organisation cannot be deleted: students are assigned to it."
    And I log out
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    Then I should see "You are assigned to: Alpine Farm (Outside wishes)."

  Scenario: An assigned student gives up the place, then requests an agreement outside the list
    Given the following "mod_stage > themes" exist:
      | stage       | name  | placementenabled | placementchoices | placementofflist |
      | Internships | Rural | 1                | 1                | 1                |
    And the following "mod_stage > placement offers" exist:
      | stage       | theme | name        |
      | Internships | Rural | Alpine Farm |
    And the following "mod_stage > placement assignments" exist:
      | stage       | theme | user     | offer       |
      | Internships | Rural | student1 | Alpine Farm |
    # Holding a place, the student cannot request an agreement elsewhere on the same theme.
    When I am on the "Internships" "mod_stage > Activity" page logged in as student1
    And I follow "Request an agreement (outside StageVet)"
    And I set the following fields to these values:
      | structure                 | Hill Farm               |
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
      | hostservice               | Cattle                  |
      | hostphone                 | 0400000002              |
      | hostemail                 | hill@example.com        |
      | siret                     | 732 829 320 00074       |
      | tutorname                 | Dr Marie Petit          |
      | tutorfunction             | Veterinarian            |
      | tutorphone                | 0400000003              |
      | tutoremail                | tutor@example.com       |
      | gratificationamount       | 0                       |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "You are assigned to \"Alpine Farm\" for this theme."
    And I should not see "The internship has been registered"
    # The student gives up the place from the offered organisations page.
    When I am on the "Internships > Rural" "mod_stage > Placement wishes" page
    Then I should see "Found an internship elsewhere? First give up your assignment"
    When I press "Give up this assignment"
    Then I should see "You have given up your assignment; the place is freed."
    And I should not see "Give up this assignment"
    # The off-list request now goes through.
    When I am on the "Internships" "mod_stage > Activity" page
    And I follow "Request an agreement (outside StageVet)"
    And I set the following fields to these values:
      | structure                 | Hill Farm               |
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
      | hostservice               | Cattle                  |
      | hostphone                 | 0400000002              |
      | hostemail                 | hill@example.com        |
      | siret                     | 732 829 320 00074       |
      | tutorname                 | Dr Marie Petit          |
      | tutorfunction             | Veterinarian            |
      | tutorphone                | 0400000003              |
      | tutoremail                | tutor@example.com       |
      | gratificationamount       | 0                       |
    And I press "Request an agreement (outside StageVet)"
    Then I should see "The internship has been registered and the agreement request sent to the DEVE."
    And I should see "You have submitted an agreement request for this theme outside the offered organisations"

  Scenario: An internship registered by the academic office at the assigned organisation uses the assignment
    Given the following "mod_stage > themes" exist:
      | stage       | name  | placementenabled | placementchoices |
      | Internships | Rural | 1                | 1                |
    And the following "mod_stage > placement offers" exist:
      | stage       | theme | name        |
      | Internships | Rural | Alpine Farm |
    And the following "mod_stage > placement assignments" exist:
      | stage       | theme | user     | offer       |
      | Internships | Rural | student1 | Alpine Farm |
    And the following "mod_stage > entries" exist:
      | stage       | user     | theme | structure   | datestart | dateend  |
      | Internships | student1 | Rural | Alpine Farm | +1 month  | +6 weeks |
      | Internships | student2 | Rural | Hill Farm   | +1 month  | +6 weeks |
    # The student requests the agreement of the registered internship: no SIRET is needed, and the
    # request uses the assignment.
    When I am on the "Internships > student1 > Rural" "mod_stage > Convention request" page logged in as student1
    And I set the following fields to these values:
      | studentaddress          | 12 rue des Lilas, Lyon  |
      | studentphone            | 0600000001              |
      | hostaddress             | 4 place du Marché, Lyon |
      | hostrepresentative      | Dr Paul Roux            |
      | hostrepresentativetitle | Director                |
      | hostservice             | Cattle                  |
      | hostphone               | 0400000002              |
      | hostemail               | farm@example.com        |
      | tutorname               | Dr Marie Petit          |
      | tutorfunction           | Veterinarian            |
      | tutorphone              | 0400000003              |
      | tutoremail              | tutor@example.com       |
      | gratificationamount     | 0                       |
    And I press "Request the agreement"
    Then I should see "Agreement request submitted."
    And I log out
    # The academic office sees where the request comes from.
    When I am on the "Internships > student1 > Rural" "mod_stage > Entry detail" page logged in as deve1
    Then I should see "Offered organisation, assigned to the student"
    And I log out
    # Registered elsewhere, on a theme without off-list requests: the SIRET is required from the student.
    When I am on the "Internships > student2 > Rural" "mod_stage > Convention request" page logged in as student2
    And I set the following fields to these values:
      | studentaddress          | 3 rue du Pont, Lyon     |
      | studentphone            | 0600000002              |
      | hostaddress             | 9 chemin du Haut        |
      | hostrepresentative      | M. Haut                 |
      | hostrepresentativetitle | Farmer                  |
      | hostservice             | Cattle                  |
      | hostphone               | 0400000012              |
      | hostemail               | hill@example.com        |
      | tutorname               | Dr Haut                 |
      | tutorfunction           | Veterinarian            |
      | tutorphone              | 0400000013              |
      | tutoremail              | haut@example.com        |
      | gratificationamount     | 0                       |
    And I press "Request the agreement"
    Then I should see "The organisation's SIRET number is required for a request outside the offered organisations"
    When I set the field "siret" to "732 829 320 00074"
    And I press "Request the agreement"
    Then I should see "The agreement request has been saved."

  Scenario: The "most students placed" rule places everyone it can, and each round can be verified
    Given the following "mod_stage > themes" exist:
      | stage       | name  | placementenabled | placementchoices | placementmode |
      | Internships | Rural | 1                | 2                | maxplaced     |
    And the following "mod_stage > placement offers" exist:
      | stage       | theme | name          | places |
      | Internships | Rural | Alpine Farm   | 1      |
      | Internships | Rural | Valley Clinic | 1      |
    And the following "mod_stage > placement wishes" exist:
      | stage       | theme | user     | offers                     |
      | Internships | Rural | student1 | Alpine Farm, Valley Clinic |
      | Internships | Rural | student2 | Alpine Farm, Valley Clinic |
    When I am on the "Internships > Rural" "mod_stage > Placement matching" page logged in as deve1
    Then I should see "Matching rule: Most students placed."
    When I press "Run the matching now"
    Then I should see "Matching done: 2 student(s) assigned, 0 without assignment."
    And I should see "Matching rounds"
    And I should see "2 of 2 assigned"
    And I should see "Claire Dupont" in the "stage-placement-rounds" "table"
    When I follow "Verify"
    Then I should see "replayed from its record: same result as the one saved."
