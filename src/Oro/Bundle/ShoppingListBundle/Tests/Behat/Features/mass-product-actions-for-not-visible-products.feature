@fixture-OroProductBundle:Products_view_page_templates.yml
Feature: Mass Product Actions for not visible products
  In order to be able to add only visible products with help of mass actions
  As a Buyer
  I should receive proper notifications when trying to add not visible product

  Scenario: Create sessions
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |
    And I proceed as the Admin
    And I login as administrator

  Scenario: Non visible products can not be added to a newly created Shopping List with mass actions
    Given I proceed as the Buyer
    Given I login as AmandaRCole@example.org buyer
    And I go to homepage
    And I type "rtsh_m" in "search"
    And I click "Search Button"
    Then I should see "rtsh_m"
    And I check rtsh_m record in "Product Frontend Grid" grid

    When I proceed as the Admin
    And I go to Products/ Products
    And I click view rtsh_m in grid
    And click "More actions"
    And click "Manage Visibility"
    And I select "Hidden" from "Visibility to All"
    And I save and close form
    Then I should see "Product visibility has been saved" flash message

    When I proceed as the Buyer
    And I click "Create New Shopping List" link from mass action dropdown in "Product Frontend Grid"
    And I click "Create and Add"
    Then I should see "No products were added"
    And I should not see "rtsh_m"

  Scenario: Non visible products can not be added with mass actions
    Given I proceed as the Buyer
    And I go to homepage
    And I type "gtsh_l" in "search"
    And I click "Search Button"
    Then I should see "gtsh_l"
    And I check gtsh_l record in "Product Frontend Grid" grid

    When I proceed as the Admin
    And I go to Products/ Products
    And I click view gtsh_l in grid
    And click "More actions"
    And click "Manage Visibility"
    And I select "Hidden" from "Visibility to All"
    And I save and close form
    Then I should see "Product visibility has been saved" flash message

    When I proceed as the Buyer
    And I click "Add to current Shopping List" link from mass action dropdown in "Product Frontend Grid"
    Then I should see "No products were added"
    And I should not see "gtsh_l"
