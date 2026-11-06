export type CatalogSpace = {
    id: number;
    name: string;
    slug: string;
    category: string;
    category_label: string;
    base_price: number;
};

export type CatalogVenue = {
    id: number;
    name: string;
    slug: string;
    city: string;
    address: string;
    cover_photo_path: string | null;
    spaces_count: number;
    starting_price: number;
    spaces: CatalogSpace[];
};

export type CategoryOption = {
    value: string;
    label: string;
    count: number;
};

export type HomePageProps = {
    venues: CatalogVenue[];
    cities: string[];
    categories: CategoryOption[];
};

export type Amenity = {
    id: number;
    name: string;
    icon: string;
};

export type VenueReference = {
    id: number;
    name: string;
    slug: string;
    city: string;
    timezone: string;
    cover_photo_path: string | null;
};

export type SpaceListItem = {
    id: number;
    name: string;
    slug: string;
    category: string;
    category_label: string;
    capacity: number | null;
    base_price: number;
    instant_book: boolean;
    venue: VenueReference;
    amenities: Amenity[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
    links: PaginationLink[];
};

export type SpaceFilters = {
    q: string | null;
    city: string | null;
    category: string | null;
    date: string | null;
    min_price: number | null;
    max_price: number | null;
};

export type SpaceSort = 'price_asc' | 'price_desc';

export type SpacesIndexPageProps = {
    spaces: Paginator<SpaceListItem>;
    filters: SpaceFilters;
    cities: string[];
    categories: { value: string; label: string }[];
    priceRange: { min: number; max: number };
    sort: SpaceSort;
};

export type OperatingHour = {
    day_of_week: number;
    open_time: string;
    close_time: string;
    slot_duration_minutes: number;
};
export type SlotCell = {
    start_time: string;
    end_time: string;
    price: number;
    status: 'available' | 'booked' | 'past';
};

export type SlotResponse = {
    date: string;
    timezone: string;
    slot_duration_minutes: number | null;
    slots: SlotCell[];
};

export type AddOn = {
    id: number;
    name: string;
    price: number;
    pricing_type: string;
    pricing_type_label: string;
    stock_quantity: number | null;
};

export type SpaceDetail = {
    id: number;
    name: string;
    slug: string;
    category: string;
    category_label: string;
    capacity: number;
    base_price: number;
    instant_book: boolean;
    cancellation_policy: string;
    cancellation_policy_label: string;
    cancellation_cutoff_hours: number;
    refund_percentage: number;
    amenities: Amenity[];
    add_ons: AddOn[];
    operating_hours: OperatingHour[];
};

export type SpaceDetailVenue = {
    id: number;
    name: string;
    slug: string;
    city: string;
    address: string;
    timezone: string;
    contact_phone: string | null;
};

export type SpaceShowPageProps = {
    space: SpaceDetail;
    venue: SpaceDetailVenue;
    initial_date: string;
    initial_slots: SlotResponse;
};

export type VenueSpace = {
    id: number;
    name: string;
    slug: string;
    category: string;
    category_label: string;
    capacity: number;
    base_price: number;
    amenities: Amenity[];
};

export type VenueDetail = {
    id: number;
    name: string;
    slug: string;
    city: string;
    address: string;
    timezone: string;
    cover_photo_path: string | null;
    contact_phone: string | null;
    spaces: VenueSpace[];
};

export type VenueShowPageProps = {
    venue: VenueDetail;
};

export type CheckoutSpace = {
    id: number;
    name: string;
    slug: string;
    category: string;
    category_label: string;
    base_price: number;
};

export type CheckoutVenue = {
    id: number;
    name: string;
    slug: string;
    city: string;
    address: string;
    timezone: string;
    contact_phone: string | null;
};

export type CheckoutSlot = {
    start_time: string;
    end_time: string;
    price: number;
};

export type CheckoutAddOn = {
    id: number;
    name: string;
    price: number;
    pricing_type: 'per_hour' | 'per_item' | 'per_booking';
    pricing_type_label: string;
    stock_quantity: number;
};

export type CheckoutCustomer = {
    name: string;
    email: string;
    phone: string;
};

export type CancellationPolicySnapshot = {
    policy: string;
    policy_label: string;
    cutoff_hours: number;
    refund_percentage: number;
};

export type CheckoutPricing = {
    duration_minutes: number;
    duration_hours: number;
    slot_subtotal: number;
    addons_subtotal: number;
    total_amount: number;
    slots: CheckoutSlot[];
    addons: Array<{
        space_add_on_id: number;
        name: string;
        pricing_type: string;
        unit_price: number;
        quantity: number;
        total_price: number;
    }>;
};

export type CheckoutPageProps = {
    space: CheckoutSpace;
    venue: CheckoutVenue;
    date: string;
    slots: CheckoutSlot[];
    addOns: CheckoutAddOn[];
    customer: CheckoutCustomer;
    cancellationPolicy: CancellationPolicySnapshot;
    initialPricing: CheckoutPricing;
};

export type PaymentPendingBooking = {
    code: string;
    status: string;
    status_label: string;
    booking_date: string;
    start_time: string;
    end_time: string;
    customer_name: string;
    customer_phone: string;
    customer_email: string;
    notes: string | null;
    subtotal_amount: number;
    addons_amount: number;
    total_amount: number;
    payment_method: string;
    payment_method_label: string;
    qr_checkin_token: string;
    expires_at: string | null;
    venue: {
        id: number;
        name: string;
        slug: string;
        city: string;
        address: string;
        contact_phone: string | null;
    };
    space: {
        id: number;
        name: string;
        slug: string;
        category: string;
        category_label: string;
    };
    slots: Array<{
        start_time: string;
        end_time: string;
        price: number;
    }>;
    addOns: Array<{
        name: string;
        unit_price: number;
        quantity: number;
        total_price: number;
    }>;
    cancellation_policy: CancellationPolicySnapshot | null;
};

export type PaymentPendingPageProps = {
    booking: PaymentPendingBooking;
    is_demo_mode?: boolean;
    is_confirmed?: boolean;
};

export type TicketBooking = PaymentPendingBooking & {
    checked_in_at?: string | null;
};

export type TicketPageProps = {
    booking: TicketBooking;
};

export type GateScannerVenue = {
    id: number;
    name: string;
    city: string;
    timezone: string;
};

export type GateRecentScan = {
    code: string;
    customer_name: string;
    space_name: string;
    booking_date: string;
    start_time: string;
    end_time: string;
    checked_in_at: string;
};

export type GateScannerProps = {
    venues: GateScannerVenue[];
    selected_venue_id: number | null;
    recent_scans: GateRecentScan[];
};
