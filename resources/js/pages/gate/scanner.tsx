import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Camera,
    CameraOff,
    CheckCircle2,
    Clock,
    Keyboard,
    LayoutDashboard,
    MapPin,
    Maximize2,
    Minimize2,
    QrCode,
    RefreshCw,
    ShieldAlert,
    XCircle,
} from 'lucide-react';
import { type FormEvent, useEffect, useId, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { GateRecentScan, GateScannerProps } from '@/types/catalog';

type ScanResponseData = {
    success: boolean;
    message: string;
    error?: string;
    booking?: {
        id?: number;
        code: string;
        customer_name: string;
        customer_phone?: string;
        space_name: string;
        booking_date?: string;
        start_time?: string;
        end_time?: string;
        checked_in_at?: string;
    };
};

export default function GateScannerPage({
    venues,
    selected_venue_id,
    recent_scans,
}: GateScannerProps) {
    const [selectedVenueId, setSelectedVenueId] = useState<number | null>(
        selected_venue_id ?? (venues.length > 0 ? venues[0].id : null),
    );
    const [mode, setMode] = useState<'camera' | 'manual'>('camera');
    const [manualToken, setManualToken] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [scanResult, setScanResult] = useState<ScanResponseData | null>(null);
    const [recentList, setRecentList] =
        useState<GateRecentScan[]>(recent_scans);
    const [isFullscreen, setIsFullscreen] = useState(false);

    // Camera states
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [hasBarcodeDetector, setHasBarcodeDetector] = useState(false);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const scanLoopRef = useRef<number | null>(null);

    const tokenInputId = useId();

    const currentVenue =
        venues.find((v) => v.id === selectedVenueId) ?? venues[0] ?? null;

    useEffect(() => {
        if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
            setHasBarcodeDetector(true);
        }

        const handleFullscreenChange = () => {
            setIsFullscreen(Boolean(document.fullscreenElement));
        };
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        return () => {
            document.removeEventListener(
                'fullscreenchange',
                handleFullscreenChange,
            );
        };
    }, []);

    // Camera stream management
    useEffect(() => {
        if (mode !== 'camera') {
            stopCamera();
            return;
        }

        void startCamera();

        return () => {
            stopCamera();
        };
    }, [mode, selectedVenueId]);

    const toggleFullscreen = async () => {
        try {
            if (!document.fullscreenElement) {
                await document.documentElement.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch {
            // Fullscreen might be restricted by browser policy
        }
    };

    const startCamera = async () => {
        setCameraError(null);
        try {
            if (!navigator.mediaDevices?.getUserMedia) {
                throw new Error(
                    'Kamera tidak didukung pada browser ini. Silakan gunakan Mode Input Manual.',
                );
            }

            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });

            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
                setCameraActive(true);
                startBarcodeScanning();
            }
        } catch (err: unknown) {
            setCameraActive(false);
            if (err instanceof Error) {
                if (err.name === 'NotAllowedError') {
                    setCameraError(
                        'Izin akses kamera ditolak. Berikan izin di browser atau beralih ke Input Manual.',
                    );
                } else if (err.name === 'NotFoundError') {
                    setCameraError(
                        'Kamera tidak ditemukan pada perangkat ini. Silakan gunakan Input Manual.',
                    );
                } else {
                    setCameraError(
                        err.message || 'Gagal menyalakan stream kamera.',
                    );
                }
            } else {
                setCameraError('Gagal mengakses kamera perangkat.');
            }
        }
    };

    const stopCamera = () => {
        if (scanLoopRef.current) {
            cancelAnimationFrame(scanLoopRef.current);
            scanLoopRef.current = null;
        }

        if (streamRef.current) {
            streamRef.current.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
        }

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }

        setCameraActive(false);
    };

    const startBarcodeScanning = () => {
        if (!('BarcodeDetector' in window)) {
            return;
        }

        type NativeBarcodeDetector = {
            detect: (
                image: HTMLVideoElement,
            ) => Promise<Array<{ rawValue: string }>>;
        };

        const DetectorClass = (
            window as unknown as {
                BarcodeDetector: new (opts?: {
                    formats: string[];
                }) => NativeBarcodeDetector;
            }
        ).BarcodeDetector;

        const detector = new DetectorClass({ formats: ['qr_code'] });

        const scanFrame = async () => {
            if (
                !videoRef.current ||
                videoRef.current.readyState < HTMLMediaElement.HAVE_CURRENT_DATA
            ) {
                scanLoopRef.current = requestAnimationFrame(scanFrame);
                return;
            }

            try {
                const barcodes = await detector.detect(videoRef.current);
                if (barcodes.length > 0 && !isProcessing) {
                    const detectedToken = barcodes[0].rawValue.trim();
                    if (detectedToken) {
                        void submitToken(detectedToken);
                        return;
                    }
                }
            } catch {
                // Ignore detection errors during frames
            }

            scanLoopRef.current = requestAnimationFrame(scanFrame);
        };

        scanLoopRef.current = requestAnimationFrame(scanFrame);
    };

    const submitToken = async (tokenValue: string) => {
        if (isProcessing) return;
        setIsProcessing(true);
        setScanResult(null);

        try {
            const csrfMeta = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            );
            const csrfToken = csrfMeta?.content ?? '';

            const response = await fetch('/gate/scanner/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    token: tokenValue,
                    venue_id: selectedVenueId,
                }),
            });

            const result = (await response.json()) as ScanResponseData;
            setScanResult(result);

            if (result.success && result.booking) {
                setRecentList((prev) => [
                    {
                        code: result.booking?.code ?? '',
                        customer_name: result.booking?.customer_name ?? '',
                        space_name: result.booking?.space_name ?? '',
                        booking_date:
                            result.booking?.booking_date ??
                            new Date().toISOString().split('T')[0],
                        start_time: result.booking?.start_time ?? '',
                        end_time: result.booking?.end_time ?? '',
                        checked_in_at:
                            result.booking?.checked_in_at ??
                            new Date().toLocaleTimeString('id-ID', {
                                hour: '2-digit',
                                minute: '2-digit',
                            }),
                    },
                    ...prev.slice(0, 9),
                ]);
                setManualToken('');
            }
        } catch {
            setScanResult({
                success: false,
                message:
                    'Terjadi gangguan koneksi ke server saat memeriksa tiket. Silakan coba lagi.',
            });
        } finally {
            setIsProcessing(false);
        }
    };

    const handleManualSubmit = (e: FormEvent) => {
        e.preventDefault();
        const trimmed = manualToken.trim();
        if (trimmed) {
            void submitToken(trimmed);
        }
    };

    const handleVenueChange = (newVenueId: number) => {
        setSelectedVenueId(newVenueId);
        setScanResult(null);
        router.get(
            '/gate/scanner',
            { venue_id: newVenueId },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Gate Scanner Tiket - Pos Operasional | AdaTempat" />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                {/* Dedicated Operational Top Bar */}
                <header className="sticky top-0 z-40 flex h-16 w-full max-w-full shrink-0 items-center justify-between border-b border-stone-200/90 bg-background/95 px-4 backdrop-blur-xs sm:px-6 lg:px-8 dark:border-neutral-800">
                    {/* Brand & Mode Identification */}
                    <div className="flex items-center gap-3">
                        <Link
                            href={dashboard()}
                            className="flex items-center gap-2 text-foreground hover:opacity-80"
                            title="Kembali ke Dashboard"
                        >
                            <div className="flex size-8 items-center justify-center rounded-md bg-cobalt text-white shadow-xs">
                                <QrCode className="size-4.5 stroke-[2.2]" />
                            </div>
                            <span className="hidden font-display text-lg font-extrabold tracking-tight sm:inline">
                                AdaTempat
                            </span>
                        </Link>

                        <div className="hidden h-5 w-px bg-stone-200 sm:block dark:bg-neutral-800" />

                        <div className="flex items-center gap-2">
                            <span className="inline-flex size-2 animate-pulse rounded-full bg-emerald-600" />
                            <Badge
                                variant="secondary"
                                className="border-stone-200 bg-stone-100 text-xs font-semibold text-stone-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-stone-300"
                            >
                                Pos Gerbang (Kiosk)
                            </Badge>
                        </div>
                    </div>

                    {/* Venue Selector (Center) */}
                    {venues.length > 0 && (
                        <div className="hidden items-center gap-2 lg:flex">
                            <MapPin className="size-3.5 text-cobalt" />
                            <select
                                id="venue-select"
                                aria-label="Pilih lokasi venue gerbang"
                                value={selectedVenueId ?? ''}
                                onChange={(e) =>
                                    handleVenueChange(Number(e.target.value))
                                }
                                className="h-9 max-w-[280px] truncate rounded-md border border-stone-200 bg-background px-3 py-1 text-xs font-semibold shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt focus-visible:outline-none dark:border-neutral-700"
                            >
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name} ({v.city} · {v.timezone})
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {/* Quick Tools & Back to Panel (Right) */}
                    <div className="flex items-center gap-2">
                        {/* Toggle Fullscreen Button */}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={toggleFullscreen}
                            className="h-9 gap-1.5 border-stone-200 px-3 text-xs font-medium text-stone-700 hover:bg-stone-100 dark:border-neutral-700 dark:text-stone-300 dark:hover:bg-neutral-800"
                            title={
                                isFullscreen
                                    ? 'Keluar dari Layar Penuh'
                                    : 'Mode Layar Penuh (Fullscreen)'
                            }
                        >
                            {isFullscreen ? (
                                <>
                                    <Minimize2 className="size-3.5 text-stone-500" />
                                    <span className="hidden sm:inline">
                                        Keluar Fullscreen
                                    </span>
                                </>
                            ) : (
                                <>
                                    <Maximize2 className="size-3.5 text-stone-500" />
                                    <span className="hidden sm:inline">
                                        Layar Penuh
                                    </span>
                                </>
                            )}
                        </Button>

                        {/* Back to Owner Panel */}
                        <Button
                            variant="default"
                            size="sm"
                            asChild
                            className="h-9 gap-1.5 bg-cobalt text-xs font-semibold text-white hover:bg-cobalt-deep"
                        >
                            <Link href={dashboard()}>
                                <ArrowLeft className="size-3.5 sm:hidden" />
                                <LayoutDashboard className="hidden size-3.5 sm:inline" />
                                <span>Kembali ke Panel</span>
                            </Link>
                        </Button>
                    </div>
                </header>

                {/* Mobile Venue Selector Bar */}
                {venues.length > 1 && (
                    <div className="flex items-center justify-between border-b border-stone-200 bg-stone-50/80 px-4 py-2 text-xs lg:hidden dark:border-neutral-800 dark:bg-neutral-900/60">
                        <span className="font-medium text-stone-500">
                            Lokasi Venue:
                        </span>
                        <select
                            aria-label="Pilih lokasi venue"
                            value={selectedVenueId ?? ''}
                            onChange={(e) =>
                                handleVenueChange(Number(e.target.value))
                            }
                            className="h-8 rounded-md border border-stone-200 bg-background px-2 text-xs font-semibold dark:border-neutral-700"
                        >
                            {venues.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {/* Main Content Area */}
                <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-7xl space-y-6">
                        {/* Title & Tabs Bar */}
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h1 className="font-display text-2xl font-extrabold tracking-tight text-foreground md:text-3xl">
                                    Pemeriksaan Tiket Pengunjung
                                </h1>
                                <p className="mt-0.5 text-xs text-stone-500 sm:text-sm">
                                    Arahkan kamera ke QR tiket atau ketik kode
                                    booking untuk memvalidasi akses masuk.
                                    {currentVenue && (
                                        <span className="ms-1.5 font-semibold text-foreground">
                                            &bull; {currentVenue.name}
                                        </span>
                                    )}
                                </p>
                            </div>

                            {/* Mode Tabs */}
                            <div
                                role="tablist"
                                aria-label="Pilihan metode pemindaian"
                                className="inline-flex self-start rounded-lg border border-stone-200 bg-stone-100 p-1 sm:self-auto dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={mode === 'camera'}
                                    onClick={() => setMode('camera')}
                                    className={`inline-flex items-center gap-2 rounded-md px-4 py-2 text-xs font-semibold tracking-wider transition-colors duration-150 ${
                                        mode === 'camera'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                                    }`}
                                >
                                    <Camera className="size-3.5" />
                                    <span>Kamera Scanner</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={mode === 'manual'}
                                    onClick={() => setMode('manual')}
                                    className={`inline-flex items-center gap-2 rounded-md px-4 py-2 text-xs font-semibold tracking-wider transition-colors duration-150 ${
                                        mode === 'manual'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                                    }`}
                                >
                                    <Keyboard className="size-3.5" />
                                    <span>Input Manual</span>
                                </button>
                            </div>
                        </div>

                        {/* Operational Grid */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                            {/* Left Column: Scanner / Form (7 cols) */}
                            <div className="space-y-6 lg:col-span-7">
                                {mode === 'camera' ? (
                                    <Card className="overflow-hidden border-stone-200/90 shadow-xs dark:border-neutral-800">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="font-display text-base font-bold">
                                                Jendela Pemindai QR
                                            </CardTitle>
                                            <CardDescription className="text-xs text-stone-500">
                                                Posisikan kode QR tiket
                                                pengunjung di dalam bingkai
                                                bidik.
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent className="space-y-4">
                                            <div className="relative aspect-video w-full overflow-hidden rounded-lg bg-stone-950">
                                                <video
                                                    ref={videoRef}
                                                    className="size-full object-cover"
                                                    playsInline
                                                    muted
                                                />

                                                {/* Camera Overlay Frame */}
                                                {cameraActive && (
                                                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                                                        <div className="relative size-56 border-2 border-cobalt/80 bg-cobalt/10 sm:size-64">
                                                            {/* Scanning Laser Line */}
                                                            <div className="absolute inset-x-0 h-0.5 animate-pulse bg-cobalt shadow-[0_0_8px_rgba(37,99,235,0.8)]" />
                                                            {/* Frame Corner Accents */}
                                                            <div className="absolute -start-1 -top-1 size-4 border-s-4 border-t-4 border-white" />
                                                            <div className="absolute -end-1 -top-1 size-4 border-e-4 border-t-4 border-white" />
                                                            <div className="absolute -start-1 -bottom-1 size-4 border-s-4 border-b-4 border-white" />
                                                            <div className="absolute -end-1 -bottom-1 size-4 border-e-4 border-b-4 border-white" />
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Camera Error or Off Banner */}
                                                {(!cameraActive ||
                                                    cameraError) && (
                                                    <div className="absolute inset-0 flex flex-col items-center justify-center bg-stone-950/95 p-6 text-center text-white">
                                                        <CameraOff className="size-10 text-stone-400" />
                                                        <p className="mt-3 text-sm font-semibold">
                                                            {cameraError ??
                                                                'Kamera sedang tidak aktif'}
                                                        </p>
                                                        <div className="mt-4 flex flex-wrap gap-2">
                                                            <Button
                                                                size="sm"
                                                                onClick={
                                                                    startCamera
                                                                }
                                                                className="bg-cobalt text-white hover:bg-cobalt-deep"
                                                            >
                                                                Coba Nyalakan
                                                                Lagi
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="border-stone-700 bg-transparent text-white hover:bg-stone-800"
                                                                onClick={() =>
                                                                    setMode(
                                                                        'manual',
                                                                    )
                                                                }
                                                            >
                                                                Beralih ke Input
                                                                Manual
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}

                                                {/* Scanning Status Badge */}
                                                {cameraActive && (
                                                    <div className="absolute start-3 bottom-3 rounded-md bg-stone-900/80 px-2.5 py-1 text-xs text-white backdrop-blur-xs">
                                                        {isProcessing
                                                            ? 'Memvalidasi tiket...'
                                                            : hasBarcodeDetector
                                                              ? 'Arahkan QR tiket ke kotak'
                                                              : 'Detektor QR aktif'}
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-stone-500">
                                                <p>
                                                    Posisikan kode QR di dalam
                                                    bingkai tengah.
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setMode('manual')
                                                    }
                                                    className="font-medium text-cobalt hover:underline"
                                                >
                                                    Ketik token manual jika
                                                    kamera buram &rarr;
                                                </button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ) : (
                                    /* Manual Input Form */
                                    <Card className="border-stone-200/90 shadow-xs dark:border-neutral-800">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="font-display text-base font-bold">
                                                Input Token Manual
                                            </CardTitle>
                                            <CardDescription className="text-xs text-stone-500">
                                                Ketik token QR 64 karakter atau
                                                kode booking resmi pengunjung
                                                (ADT-BK-...).
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent>
                                            <form
                                                onSubmit={handleManualSubmit}
                                                className="space-y-4"
                                            >
                                                <div className="space-y-2">
                                                    <label
                                                        htmlFor={tokenInputId}
                                                        className="text-xs font-semibold text-foreground"
                                                    >
                                                        Token QR Check-in atau
                                                        Kode Booking
                                                    </label>
                                                    <div className="flex gap-2">
                                                        <Input
                                                            id={tokenInputId}
                                                            type="text"
                                                            value={manualToken}
                                                            onChange={(e) =>
                                                                setManualToken(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Contoh: ADT-BK-20261105-XXXXX atau token 64 karakter..."
                                                            className="h-10 border-stone-200 font-mono text-sm shadow-2xs focus-visible:border-cobalt focus-visible:ring-1 focus-visible:ring-cobalt dark:border-neutral-700"
                                                            autoFocus
                                                        />
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                isProcessing ||
                                                                !manualToken.trim()
                                                            }
                                                            className="h-10 min-w-28 gap-2 bg-cobalt text-white hover:bg-cobalt-deep"
                                                        >
                                                            {isProcessing ? (
                                                                <RefreshCw className="size-4 animate-spin" />
                                                            ) : (
                                                                <>
                                                                    <span>
                                                                        Validasi
                                                                    </span>
                                                                    <ArrowRight className="size-4" />
                                                                </>
                                                            )}
                                                        </Button>
                                                    </div>
                                                </div>
                                            </form>
                                        </CardContent>
                                    </Card>
                                )}

                                {/* Help Rules Panel */}
                                <Card className="border-stone-200/90 bg-stone-50/70 shadow-2xs dark:border-neutral-800 dark:bg-neutral-900/40">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="font-display text-xs font-bold tracking-wider text-foreground uppercase">
                                            Ketentuan Validasi Gerbang Masuk
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="space-y-1.5 text-xs text-stone-600 dark:text-stone-400">
                                            <li className="flex items-start gap-1.5">
                                                <span className="text-cobalt">
                                                    &bull;
                                                </span>
                                                <span>
                                                    <strong>
                                                        Window Waktu:
                                                    </strong>{' '}
                                                    Check-in dibuka 30 menit
                                                    sebelum jam sewa dimulai
                                                    hingga jadwal berakhir.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-1.5">
                                                <span className="text-cobalt">
                                                    &bull;
                                                </span>
                                                <span>
                                                    <strong>Single-Use:</strong>{' '}
                                                    Satu tiket hanya dapat
                                                    divalidasi tepat satu kali
                                                    secara atomik.
                                                </span>
                                            </li>
                                            <li className="flex items-start gap-1.5">
                                                <span className="text-cobalt">
                                                    &bull;
                                                </span>
                                                <span>
                                                    <strong>
                                                        Kesesuaian Venue:
                                                    </strong>{' '}
                                                    Tiket harus terdaftar di
                                                    venue yang sama dengan
                                                    gerbang ini.
                                                </span>
                                            </li>
                                        </ul>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Right Column: Result Feedback & Recent List (5 cols) */}
                            <div className="space-y-6 lg:col-span-5">
                                {/* Scan Result Feedback Card */}
                                <div
                                    role="status"
                                    aria-live="polite"
                                    className={`min-h-56 rounded-xl border p-6 shadow-xs transition-all duration-200 ${
                                        scanResult?.success
                                            ? 'border-emerald-300 bg-emerald-50/90 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200'
                                            : scanResult && !scanResult.success
                                              ? 'border-rose-300 bg-rose-50/90 text-rose-950 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-200'
                                              : 'border-dashed border-stone-300 bg-stone-50/50 text-stone-500 dark:border-neutral-700 dark:bg-neutral-900/30'
                                    }`}
                                >
                                    {scanResult ? (
                                        scanResult.success ? (
                                            /* SUCCESS STATE */
                                            <div className="space-y-4">
                                                <div className="flex items-start gap-3">
                                                    <CheckCircle2 className="size-7 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                    <div>
                                                        <Badge className="bg-emerald-600 text-white shadow-2xs">
                                                            Check-in Berhasil
                                                        </Badge>
                                                        <h3 className="mt-1 font-display text-xl font-bold text-foreground">
                                                            {scanResult.booking
                                                                ?.customer_name ??
                                                                'Pengunjung'}
                                                        </h3>
                                                        <p className="text-xs text-emerald-800 dark:text-emerald-300">
                                                            {scanResult.message}
                                                        </p>
                                                    </div>
                                                </div>

                                                {scanResult.booking && (
                                                    <div className="space-y-1.5 border-t border-emerald-300/60 pt-3 text-xs dark:border-emerald-800/60">
                                                        <div className="flex justify-between">
                                                            <span className="text-stone-600 dark:text-stone-400">
                                                                Kode Booking:
                                                            </span>
                                                            <span className="font-mono font-semibold text-foreground">
                                                                {
                                                                    scanResult
                                                                        .booking
                                                                        .code
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-stone-600 dark:text-stone-400">
                                                                Lapangan /
                                                                Ruang:
                                                            </span>
                                                            <span className="font-semibold text-foreground">
                                                                {
                                                                    scanResult
                                                                        .booking
                                                                        .space_name
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-stone-600 dark:text-stone-400">
                                                                Jadwal Bermain:
                                                            </span>
                                                            <span className="font-semibold text-foreground">
                                                                {
                                                                    scanResult
                                                                        .booking
                                                                        .start_time
                                                                }{' '}
                                                                -{' '}
                                                                {
                                                                    scanResult
                                                                        .booking
                                                                        .end_time
                                                                }
                                                            </span>
                                                        </div>
                                                        {scanResult.booking
                                                            .checked_in_at && (
                                                            <div className="flex justify-between font-medium text-emerald-900 dark:text-emerald-300">
                                                                <span>
                                                                    Waktu Masuk:
                                                                </span>
                                                                <span className="font-mono font-bold">
                                                                    {
                                                                        scanResult
                                                                            .booking
                                                                            .checked_in_at
                                                                    }
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}

                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setScanResult(null)
                                                    }
                                                    className="mt-2 w-full border-emerald-300 bg-white/80 text-emerald-900 hover:bg-white dark:border-emerald-700 dark:bg-neutral-900 dark:text-emerald-200"
                                                >
                                                    Scan Tiket Berikutnya
                                                </Button>
                                            </div>
                                        ) : (
                                            /* ERROR / REJECTED STATE */
                                            <div className="space-y-4">
                                                <div className="flex items-start gap-3">
                                                    {scanResult.error ===
                                                    'WRONG_VENUE' ? (
                                                        <MapPin className="size-7 shrink-0 text-amber-600 dark:text-amber-400" />
                                                    ) : scanResult.error ===
                                                      'ALREADY_CHECKED_IN' ? (
                                                        <ShieldAlert className="size-7 shrink-0 text-rose-600 dark:text-rose-400" />
                                                    ) : (
                                                        <XCircle className="size-7 shrink-0 text-rose-600 dark:text-rose-400" />
                                                    )}
                                                    <div>
                                                        <Badge
                                                            variant="destructive"
                                                            className="shadow-2xs"
                                                        >
                                                            {scanResult.error ??
                                                                'Pemeriksaan Ditolak'}
                                                        </Badge>
                                                        <h3 className="mt-1 font-display text-lg font-bold text-foreground">
                                                            Akses Masuk Ditolak
                                                        </h3>
                                                        <p className="text-xs text-rose-900 dark:text-rose-300">
                                                            {scanResult.message}
                                                        </p>
                                                    </div>
                                                </div>

                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setScanResult(null)
                                                    }
                                                    className="mt-2 w-full border-rose-300 bg-white/80 text-rose-950 hover:bg-white dark:border-rose-800 dark:bg-neutral-900 dark:text-rose-200"
                                                >
                                                    Coba Scan Lagi
                                                </Button>
                                            </div>
                                        )
                                    ) : (
                                        /* IDLE STATE */
                                        <div className="flex flex-col items-center justify-center py-8 text-center">
                                            <QrCode className="size-12 text-stone-300 dark:text-neutral-700" />
                                            <h3 className="mt-3 font-display text-sm font-semibold text-foreground">
                                                Menunggu Pemindaian Tiket
                                            </h3>
                                            <p className="mt-1 text-xs text-stone-500">
                                                Arahkan kamera ke QR code tiket
                                                atau masukkan token manual pada
                                                formulir sebelah kiri.
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* Recent Scans Feed */}
                                <Card className="border-stone-200/90 shadow-xs dark:border-neutral-800">
                                    <CardHeader className="p-4 pb-2">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Clock className="size-4 text-stone-500" />
                                                <CardTitle className="font-display text-sm font-bold">
                                                    Aktivitas Check-in Terkini
                                                </CardTitle>
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                className="text-[11px] font-medium text-stone-600 dark:text-stone-400"
                                            >
                                                {recentList.length} Pengunjung
                                            </Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="p-4 pt-1">
                                        {recentList.length > 0 ? (
                                            <div className="divide-y divide-stone-100 dark:divide-neutral-800">
                                                {recentList.map((scan) => (
                                                    <div
                                                        key={scan.code}
                                                        className="flex items-center justify-between py-2.5 text-xs"
                                                    >
                                                        <div>
                                                            <p className="font-semibold text-foreground">
                                                                {
                                                                    scan.customer_name
                                                                }
                                                            </p>
                                                            <p className="text-stone-500">
                                                                {
                                                                    scan.space_name
                                                                }{' '}
                                                                (
                                                                {
                                                                    scan.start_time
                                                                }
                                                                -{scan.end_time}
                                                                )
                                                            </p>
                                                        </div>
                                                        <div className="text-end">
                                                            <span className="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                                                <CheckCircle2 className="size-3" />
                                                                {
                                                                    scan.checked_in_at
                                                                }
                                                            </span>
                                                            <p className="font-mono text-[11px] text-stone-400">
                                                                {scan.code}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="py-6 text-center text-xs text-stone-500">
                                                Belum ada pengunjung yang
                                                check-in di venue ini hari ini.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
