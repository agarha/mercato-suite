$ErrorActionPreference = "Stop"

$baseUrl = $env:MERCATO_E2E_BASE_URL
if (!$baseUrl) {
    $baseUrl = "http://localhost:8092"
}

$secret = $env:MERCATO_TEST_API_SECRET
if (!$secret) {
    $secret = "mercato-local-test-secret"
}

function Invoke-MercatoApi {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [string]$Method = "GET",
        [object]$Body = $null
    )

    $headers = @{ "X-Mercato-Test-Secret" = $secret }
    $uri = "$baseUrl/t/gigsii/?rest_route=/mercato/v1$Path"
    if ($Body -eq $null) {
        return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers
    }

    return Invoke-RestMethod -Uri $uri -Method $Method -Headers $headers -ContentType "application/json" -Body ($Body | ConvertTo-Json -Depth 20)
}

function Convert-ToSlug {
    param([Parameter(Mandatory = $true)][string]$Name)
    return (($Name.ToLowerInvariant() -replace '[^a-z0-9]+', '-').Trim('-'))
}

function Get-Categories {
    return @(Invoke-MercatoApi -Path "/categories" | ForEach-Object { $_ })
}

$script:CategoriesBySlug = @{}
$script:CategoriesByParentAndName = @{}
foreach ($category in (Get-Categories)) {
    $script:CategoriesBySlug[$category.slug] = $category
    $parentKey = if ($category.parent_id) { [string]$category.parent_id } else { "root" }
    $script:CategoriesByParentAndName["$parentKey::$($category.name)"] = $category
}

function Ensure-Category {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [int]$ParentId = 0,
        [string]$ParentSlug = "",
        [int]$SortOrder = 0
    )

    $slug = Convert-ToSlug -Name $Name
    if ($ParentId -gt 0) {
        $slugPrefix = if ($ParentSlug -ne "") { $ParentSlug } else { [string]$ParentId }
        $slug = "$slugPrefix-$slug"
    }

    $parentKey = if ($ParentId -gt 0) { [string]$ParentId } else { "root" }
    $nameKey = "$parentKey::$Name"
    if ($script:CategoriesByParentAndName.ContainsKey($nameKey)) {
        return $script:CategoriesByParentAndName[$nameKey]
    }

    if ($script:CategoriesBySlug.ContainsKey($slug)) {
        return $script:CategoriesBySlug[$slug]
    }

    $body = @{
        name = $Name
        slug = $slug
        sort_order = $SortOrder
    }
    if ($ParentId -gt 0) {
        $body.parent_id = $ParentId
    }

    $category = Invoke-MercatoApi -Path "/categories" -Method "POST" -Body $body
    $script:CategoriesBySlug[$slug] = $category
    $script:CategoriesByParentAndName[$nameKey] = $category
    return $category
}

# Source: official Taskrabbit services pages and tasker category support pages.
# https://www.taskrabbit.com/services
# https://www.taskrabbit.ca/services
# https://support.taskrabbit.com/hc/en-ca/articles/46260534390555-What-Types-of-Tasks-Can-I-Do-as-a-Tasker
$taxonomy = [ordered]@{
    "Featured Tasks" = @(
        "Furniture Assembly", "Home Repairs", "Help Moving", "Yard Work Services", "Spring Cleaning", "TV Mounting",
        "Plumbing", "Hang Art, Mirror & Decor", "Electrical Help", "Wait in Line", "Closet Organization Service"
    )
    "Handyman" = @(
        "Door, Cabinet, & Furniture Repair", "Appliance Installation & Repairs", "Furniture Assembly", "TV Mounting",
        "Drywall Repair Service", "Flooring & Tiling Help", "Electrical Help", "Sealing & Caulking", "Plumbing",
        "Window & Blinds Repair", "Ceiling Fan Installation", "Smart Home Installation", "Heavy Lifting",
        "Install Air Conditioner", "Painting", "Install Shelves, Rods & Hooks", "Home Maintenance",
        "Install Blinds & Window Treatments", "Home Repairs", "Baby Proofing", "Yard Work Services",
        "Light Installation", "Carpentry Services", "Hang Art, Mirror & Decor", "General Mounting",
        "Cabinet Installation", "Wallpapering Service", "Fence Installation & Repair Services",
        "Deck Restoration Services", "Doorbell Installation", "Home Theater Installing"
    )
    "Moving Services" = @(
        "Help Moving", "Truck Assisted Help Moving", "Packing Services & Help", "Unpacking Services", "Heavy Lifting",
        "Local Movers", "Junk Pickup", "Furniture Movers", "One Item Movers", "Storage Unit Moving", "Couch Removal",
        "Mattress Pick-Up & Removal", "Furniture Removal", "Pool Table Movers", "Appliance Removal",
        "Heavy Furniture Moving", "Rearranging Furniture", "Full Service Help Moving", "In-Home Furniture Movers"
    )
    "Furniture Assembly" = @(
        "Furniture Assembly", "Patio Furniture Assembly", "Desk Assembly", "Dresser Assembly", "Bed Assembly",
        "Bookshelf Assembly", "Couch Assembly", "Chair Assembly", "Wardrobe Assembly", "Table Assembly",
        "Disassemble Furniture"
    )
    "Mounting & Installation" = @(
        "TV Mounting", "Install Shelves, Rods & Hooks", "Ceiling Fan Installation", "Install Blinds & Window Treatments",
        "Hang Art, Mirror & Decor", "General Mounting", "Hang Christmas Lights"
    )
    "Cleaning" = @(
        "House Cleaning Services", "Deep Cleaning", "Disinfecting Services", "Move In Cleaning", "Move Out Cleaning",
        "Vacation Rental Cleaning", "Carpet Cleaning Service", "Garage Cleaning", "One Time Cleaning Services",
        "Car Washing", "Laundry Help", "Pressure Washing", "Spring Cleaning"
    )
    "Shopping + Delivery" = @(
        "Delivery Service", "Grocery Shopping & Delivery", "Running Your Errands", "Christmas Tree Delivery",
        "Wait in Line", "Deliver Big Piece of Furniture", "Drop Off Donations", "Contactless Delivery",
        "Pet Food Delivery", "Baby Food Delivery", "Return Items", "Wait for Delivery", "Shipping",
        "Breakfast Delivery", "Coffee Delivery"
    )
    "IKEA Services" = @(
        "Light Installation", "Furniture Removal", "Smart Home Installation", "Organization", "Furniture Assembly",
        "General Mounting"
    )
    "Yardwork Services" = @(
        "Gardening Services", "Weed Removal", "Lawn Care Services", "Lawn Mowing Services", "Landscaping Services",
        "Gutter Cleaning", "Tree Trimming Service", "Vacation Plant Watering", "Patio Cleaning", "Hot Tub Cleaning",
        "Fence Installation & Repair Services", "Deck Restoration Services", "Patio Furniture Assembly",
        "Fence Staining", "Mulching Services", "Lawn Fertilizer Service", "Hedge Trimming Service",
        "Outdoor Party Setup", "Urban Gardening Service", "Leaf Raking & Removal", "Produce Gardening",
        "Hose Installation", "Shed Maintenance", "Pressure Washing"
    )
    "Holidays" = @(
        "Gift Wrapping Services", "Hang Christmas Lights", "Christmas Tree Delivery", "Holiday Decorating",
        "Party Cleaning", "Toy Assembly Service", "Wait in Line", "Christmas Tree Removal"
    )
    "Winter Tasks" = @(
        "Snow Removal", "Sidewalk Salting", "Window Winterization", "Residential Snow Removal",
        "Christmas Tree Removal", "AC Winterization", "Winter Yardwork", "Pipe Insulation",
        "Storm Door Installation", "Winter Deck Maintenance", "Water Heater Maintenance", "Wait in Line"
    )
    "Personal Assistant" = @(
        "Personal Assistant", "Running Your Errands", "Wait in Line", "Organization", "Organize Home",
        "Closet Organization Service", "Interior Design Service", "Virtual Assistant"
    )
    "Baby Prep" = @(
        "Baby Proofing", "Baby Food Delivery", "Organize a Room", "Painting", "Toy Assembly Service",
        "Smart Home Installation", "Shopping", "General Cleaning"
    )
    "Virtual & Online Tasks" = @(
        "Virtual Assistant", "Organization", "Data Entry", "Computer Help"
    )
    "Office Services" = @(
        "Office Cleaning", "Office Tech Setup", "Office Movers", "Office Supply & Snack Delivery",
        "Office Furniture Assembly", "Office Setup & Organization", "Office Administration",
        "Office Interior Design", "Moving Office Furniture", "Office Mounting Service"
    )
    "Contactless Tasks" = @(
        "Contactless Delivery", "Contactless Prescription Pick-up & Delivery", "Running Your Errands",
        "Grocery Shopping & Delivery", "Disinfecting Services", "Drop Off Donations", "Yard Work Services",
        "Virtual Assistant"
    )
}

$createdParents = 0
$createdChildren = 0
$sort = 0
foreach ($parentName in $taxonomy.Keys) {
    $beforeParents = $script:CategoriesBySlug.Count
    $parent = Ensure-Category -Name $parentName -SortOrder $sort
    $afterParents = $script:CategoriesBySlug.Count
    if ($afterParents -gt $beforeParents) { $createdParents++ }

    $childSort = 0
    $parentSlug = Convert-ToSlug -Name $parentName
    foreach ($childName in $taxonomy[$parentName]) {
        $beforeChildren = $script:CategoriesBySlug.Count
        Ensure-Category -Name $childName -ParentId ([int]$parent.category_id) -ParentSlug $parentSlug -SortOrder $childSort | Out-Null
        $afterChildren = $script:CategoriesBySlug.Count
        if ($afterChildren -gt $beforeChildren) { $createdChildren++ }
        $childSort++
    }
    $sort++
}

$all = Get-Categories
$parents = @($all | Where-Object { $_.parent_id -eq $null -or $_.parent_id -eq "" })
$children = @($all | Where-Object { $_.parent_id -ne $null -and $_.parent_id -ne "" })

[pscustomobject]@{
    status = "seeded"
    tenant = "gigsii"
    parent_categories = $parents.Count
    subcategories = $children.Count
    created_parent_categories = $createdParents
    created_subcategories = $createdChildren
    source = "Taskrabbit public services taxonomy"
} | ConvertTo-Json -Depth 5
