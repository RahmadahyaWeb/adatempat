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
    capacity: number;
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
    city: string | null;
    category: string | null;
    date: string | null;
    min_price: number | null;
    max_price: number | null;
};

export type SpacesIndexPageProps = {
    spaces: Paginator<SpaceListItem>;
    filters: SpaceFilters;
    cities: string[];
    categories: { value: string; label: string }[];
    priceRange: { min: number; max: number };
};

export type OperatingHour = {
    day_of_week: number;
    open_time: string;
    close_time: string;
    slot_duration_minutes: number;
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
