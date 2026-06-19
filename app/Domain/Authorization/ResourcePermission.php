<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

// @mago-ignore lint:too-many-enum-cases
enum ResourcePermission: string
{
    // Members
    case ViewAnyMembers = 'view_any_members';
    case ViewMembers = 'view_members';
    case CreateMembers = 'create_members';
    case UpdateMembers = 'update_members';
    case DeleteMembers = 'delete_members';
    case DeleteAnyMembers = 'delete_any_members';

    // Member Field-Level Access (not part of standard CRUD)
    case ViewMemberPaymentInformation = 'view_member_payment_information';
    case UpdateMemberPaymentInformation = 'update_member_payment_information';
    case ViewMemberAddressInformation = 'view_member_address_information';
    case UpdateMemberAddressInformation = 'update_member_address_information';
    case ViewMemberRegistrationData = 'view_member_registration_data';
    case UpdateMemberRegistrationData = 'update_member_registration_data';

    // Memberships
    case ViewAnyMemberships = 'view_any_memberships';
    case ViewMemberships = 'view_memberships';
    case CreateMemberships = 'create_memberships';
    case UpdateMemberships = 'update_memberships';
    case DeleteMemberships = 'delete_memberships';
    case DeleteAnyMemberships = 'delete_any_memberships';

    // Households
    case ViewAnyHouseholds = 'view_any_households';
    case ViewHouseholds = 'view_households';
    case CreateHouseholds = 'create_households';
    case UpdateHouseholds = 'update_households';
    case DeleteHouseholds = 'delete_households';
    case DeleteAnyHouseholds = 'delete_any_households';

    // Member Objects
    case ViewAnyMemberObjects = 'view_any_member_objects';
    case ViewMemberObjects = 'view_member_objects';
    case CreateMemberObjects = 'create_member_objects';
    case UpdateMemberObjects = 'update_member_objects';
    case DeleteMemberObjects = 'delete_member_objects';
    case DeleteAnyMemberObjects = 'delete_any_member_objects';

    // Invoices
    case ViewAnyInvoices = 'view_any_invoices';
    case ViewInvoices = 'view_invoices';
    case CreateInvoices = 'create_invoices';
    case UpdateInvoices = 'update_invoices';
    case DeleteInvoices = 'delete_invoices';
    case DeleteAnyInvoices = 'delete_any_invoices';

    // Invoice Batches
    case ViewAnyInvoiceBatches = 'view_any_invoice_batches';
    case ViewInvoiceBatches = 'view_invoice_batches';
    case CreateInvoiceBatches = 'create_invoice_batches';
    case UpdateInvoiceBatches = 'update_invoice_batches';
    case DeleteInvoiceBatches = 'delete_invoice_batches';
    case DeleteAnyInvoiceBatches = 'delete_any_invoice_batches';

    // Activities
    case ViewAnyActivities = 'view_any_activities';
    case ViewActivities = 'view_activities';
    case CreateActivities = 'create_activities';
    case UpdateActivities = 'update_activities';
    case DeleteActivities = 'delete_activities';
    case DeleteAnyActivities = 'delete_any_activities';

    // Outgoing Emails
    case ViewAnyOutgoingEmails = 'view_any_outgoing_emails';
    case ViewOutgoingEmails = 'view_outgoing_emails';

    // Member Object Types
    case ViewAnyMemberObjectTypes = 'view_any_member_object_types';
    case ViewMemberObjectTypes = 'view_member_object_types';
    case CreateMemberObjectTypes = 'create_member_object_types';
    case UpdateMemberObjectTypes = 'update_member_object_types';
    case DeleteMemberObjectTypes = 'delete_member_object_types';
    case DeleteAnyMemberObjectTypes = 'delete_any_member_object_types';

    // Extra Membership Items
    case ViewAnyExtraMembershipItems = 'view_any_extra_membership_items';
    case ViewExtraMembershipItems = 'view_extra_membership_items';
    case CreateExtraMembershipItems = 'create_extra_membership_items';
    case UpdateExtraMembershipItems = 'update_extra_membership_items';
    case DeleteExtraMembershipItems = 'delete_extra_membership_items';
    case DeleteAnyExtraMembershipItems = 'delete_any_extra_membership_items';

    // Users
    case ViewAnyUsers = 'view_any_users';
    case ViewUsers = 'view_users';
    case CreateUsers = 'create_users';
    case UpdateUsers = 'update_users';
    case DeleteUsers = 'delete_users';
    case DeleteAnyUsers = 'delete_any_users';

    // Storage Spaces
    case ViewAnyStorageSpaces = 'view_any_storage_spaces';
    case ViewStorageSpaces = 'view_storage_spaces';
    case CreateStorageSpaces = 'create_storage_spaces';
    case UpdateStorageSpaces = 'update_storage_spaces';
    case DeleteStorageSpaces = 'delete_storage_spaces';
    case DeleteAnyStorageSpaces = 'delete_any_storage_spaces';

    // Storage Space Locations
    case ViewAnyStorageSpaceLocations = 'view_any_storage_space_locations';
    case ViewStorageSpaceLocations = 'view_storage_space_locations';
    case CreateStorageSpaceLocations = 'create_storage_space_locations';
    case UpdateStorageSpaceLocations = 'update_storage_space_locations';
    case DeleteStorageSpaceLocations = 'delete_storage_space_locations';
    case DeleteAnyStorageSpaceLocations = 'delete_any_storage_space_locations';

    // Storage Space Rentals
    case ViewAnyStorageSpaceRentals = 'view_any_storage_space_rentals';
    case ViewStorageSpaceRentals = 'view_storage_space_rentals';
    case CreateStorageSpaceRentals = 'create_storage_space_rentals';
    case UpdateStorageSpaceRentals = 'update_storage_space_rentals';
    case DeleteStorageSpaceRentals = 'delete_storage_space_rentals';
    case DeleteAnyStorageSpaceRentals = 'delete_any_storage_space_rentals';

    // Storage Space Locations
    case ViewAnyBillableItemInstances = 'view_any_billable_item_instances';
    case ViewBillableItemInstances = 'view_billable_item_instances';
    case CreateBillableItemInstances = 'create_billable_item_instances';
    case UpdateBillableItemInstances = 'update_billable_item_instances';
    case DeleteBillableItemInstances = 'delete_billable_item_instances';
    case DeleteAnyBillableItemInstances = 'delete_any_billable_item_instances';
}
