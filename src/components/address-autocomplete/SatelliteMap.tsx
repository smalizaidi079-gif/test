import { useEffect, useRef, useState } from "react";
import { motion } from "framer-motion";
import { isGoogleMapsConfigured, loadGoogleMaps } from "@/lib/googleMapsLoader";
import { MapPin } from "@/components/ui/icons";

interface SatelliteMapProps {
  lat?: number;
  lng?: number;
  label: string;
}

type Status = "loading" | "ready" | "error";

export function SatelliteMap({ lat, lng, label }: SatelliteMapProps) {
  const mapRef = useRef<HTMLDivElement>(null);
  const [status, setStatus] = useState<Status>("loading");

  useEffect(() => {
    if (!isGoogleMapsConfigured() || lat === undefined || lng === undefined) return;

    let cancelled = false;
    setStatus("loading");

    loadGoogleMaps()
      .then((g) => {
        if (cancelled || !mapRef.current) return;
        const map = new g.maps.Map(mapRef.current, {
          center: { lat, lng },
          zoom: 19,
          mapTypeId: g.maps.MapTypeId.HYBRID,
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: false,
          zoomControl: true,
        });
        new g.maps.Marker({ position: { lat, lng }, map, title: label });
        setStatus("ready");
      })
      .catch(() => {
        if (!cancelled) setStatus("error");
      });

    return () => {
      cancelled = true;
    };
  }, [lat, lng, label]);

  if (!isGoogleMapsConfigured()) {
    return (
      <div className="mt-4 flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center">
        <MapPin className="h-5 w-5 text-slate-300" />
        <p className="text-sm font-medium text-slate-500">Satellite view unavailable in this demo</p>
        <p className="text-xs text-slate-400">
          Add a Google Maps API key (<code>VITE_GOOGLE_MAPS_API_KEY</code>) to show live satellite
          imagery of the property here.
        </p>
      </div>
    );
  }

  if (lat === undefined || lng === undefined) return null;

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      className="mt-4 overflow-hidden rounded-2xl border border-slate-100 shadow-card"
    >
      <div ref={mapRef} className="h-56 w-full bg-slate-100 sm:h-64" />
      {status === "loading" && (
        <p className="border-t border-slate-100 p-2.5 text-center text-xs text-slate-400">
          Loading satellite imagery…
        </p>
      )}
      {status === "error" && (
        <p className="border-t border-slate-100 p-2.5 text-center text-xs text-red-500">
          Couldn't load satellite imagery — check the API key and enabled APIs.
        </p>
      )}
    </motion.div>
  );
}
