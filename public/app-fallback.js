document.addEventListener("DOMContentLoaded", () => {
    const toggles = document.querySelectorAll("[data-toggle-target]");

    toggles.forEach((toggle) => {
        toggle.addEventListener("click", (event) => {
            event.stopPropagation();
            const target = document.getElementById(toggle.dataset.toggleTarget);

            if (!target) {
                return;
            }

            const willOpen = target.hasAttribute("hidden");

            document.querySelectorAll("[data-toggle-panel]").forEach((panel) => {
                panel.setAttribute("hidden", "");
            });

            if (willOpen) {
                target.removeAttribute("hidden");
            }
        });
    });

    document.addEventListener("click", () => {
        document.querySelectorAll("[data-toggle-panel]").forEach((panel) => {
            panel.setAttribute("hidden", "");
        });
    });

    initializeOpportunityMap();
});

function initializeOpportunityMap() {
    const mapElement = document.querySelector("[data-opportunity-map]");

    if (!mapElement) {
        return;
    }

    const boot = () => {
        if (!window.L) {
            window.setTimeout(boot, 120);
            return;
        }

        const copy = JSON.parse(document.getElementById("opportunity-map-template").textContent);
        const zones = JSON.parse(mapElement.dataset.opportunityMap || "[]");
        const defaultLat = Number.parseFloat(mapElement.dataset.mapDefaultLat);
        const defaultLng = Number.parseFloat(mapElement.dataset.mapDefaultLng);
        const defaultZoom = Number.parseInt(mapElement.dataset.mapDefaultZoom, 10);
        const statusElement = document.querySelector("[data-map-status]");
        const driverCard = document.querySelector("[data-driver-card]");
        const driverTitle = document.querySelector("[data-driver-title]");
        const driverCopy = document.querySelector("[data-driver-copy]");
        const zoneList = document.querySelector("[data-map-zone-list]");

        const map = L.map(mapElement, {
            zoomControl: true,
            attributionControl: true,
        }).setView([defaultLat, defaultLng], defaultZoom);

        L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors",
        }).addTo(map);

        const zoneMarkers = zones.map((zone) => {
            const zoneColor = getPayColor(zone.pay_label);
            const hotspotCircle = L.circle([zone.latitude, zone.longitude], {
                radius: zone.hotspot_radius_m || 950,
                color: zoneColor,
                weight: 1.5,
                fillColor: zoneColor,
                fillOpacity: Math.max(0.16, (zone.pay_intensity || 40) / 220),
            }).addTo(map);

            const marker = L.marker([zone.latitude, zone.longitude]).addTo(map);
            marker.bindPopup(
                `<strong>${zone.zone_name}</strong><br>${zone.pay_label} • Score ${zone.predicted_score}<br>${zone.best_window}<br>R$ ${Number(zone.avg_fare).toFixed(2).replace(".", ",")}`
            );

            hotspotCircle.bindPopup(
                `<strong>${zone.zone_name}</strong><br>Regiao ${zone.pay_label.toLowerCase()}<br>Media R$ ${Number(zone.avg_fare).toFixed(2).replace(".", ",")}`
            );

            return { ...zone, marker, hotspotCircle };
        });

        const fallbackBounds = L.latLngBounds(zoneMarkers.map((zone) => [zone.latitude, zone.longitude]));
        if (zoneMarkers.length) {
            map.fitBounds(fallbackBounds, { padding: [32, 32] });
        }

        if (statusElement) {
            statusElement.textContent = copy.loadingStatus;
        }

        const renderZoneList = (sortedZones, latlng) => {
            if (!zoneList) {
                return;
            }

            zoneList.innerHTML = "";

            sortedZones.slice(0, 3).forEach((zone, index) => {
                const item = document.createElement("article");
                item.className = "map-zone-card";
                item.innerHTML = `
                    <div class="spread-row">
                        <h3 class="card-title">${index === 0 ? "Melhor perto de voce: " : ""}${zone.zone_name}</h3>
                        <span class="score-badge">Score ${zone.predicted_score}</span>
                    </div>
                    <p class="profile-copy">${zone.recommendation}</p>
                    <div class="detail-row">
                        <span>${zone.best_window}</span>
                        <span class="sky">${zone.distance_km ? `${zone.distance_km.toFixed(1)} ${copy.distanceSuffix}` : "zona de referencia"}</span>
                    </div>
                    <div class="detail-row">
                        <span>${zone.pay_label}</span>
                        <span>R$ ${Number(zone.avg_fare).toFixed(2).replace(".", ",")}</span>
                    </div>
                `;

                item.addEventListener("click", () => {
                    map.flyTo([zone.latitude, zone.longitude], 14, { duration: 1.2 });
                    zone.marker.openPopup();
                    zone.hotspotCircle.openPopup();
                });

                zoneList.appendChild(item);
            });

            if (latlng && driverCard && driverTitle && driverCopy) {
                driverCard.hidden = false;
                driverTitle.textContent = `Voce esta em ${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)}`;
                driverCopy.textContent = `A melhor zona perto de voce agora e ${sortedZones[0].zone_name}. Toque nas sugestoes para centralizar o mapa.`;
            }
        };

        const sortByDistanceAndScore = (latlng) => {
            return zoneMarkers
                .map((zone) => {
                    const distanceKm = haversineKm(latlng.lat, latlng.lng, zone.latitude, zone.longitude);
                    const distancePenalty = Math.min(distanceKm * 2.8, 30);
                    const localPriority = zone.predicted_score - distancePenalty;

                    return {
                        ...zone,
                        distance_km: distanceKm,
                        local_priority: localPriority,
                    };
                })
                .sort((left, right) => right.local_priority - left.local_priority);
        };

        const handleSuccess = (position) => {
            const latlng = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            };

            const driverMarker = L.circleMarker([latlng.lat, latlng.lng], {
                radius: 10,
                color: "#f97316",
                weight: 3,
                fillColor: "#fdba74",
                fillOpacity: 0.9,
            }).addTo(map);

            driverMarker.bindPopup(`<strong>${copy.userPopupTitle}</strong><br>${copy.userPopupBody}`).openPopup();
            map.flyTo([latlng.lat, latlng.lng], 13, { duration: 1.2 });

            const sortedZones = sortByDistanceAndScore(latlng);
            renderZoneList(sortedZones, latlng);

            if (statusElement) {
                statusElement.textContent = copy.readyStatus;
            }
        };

        const handleError = () => {
            renderZoneList(zoneMarkers);

            if (statusElement) {
                statusElement.textContent = copy.fallbackStatus;
            }
        };

        if (!navigator.geolocation) {
            handleError();
            return;
        }

        navigator.geolocation.getCurrentPosition(handleSuccess, handleError, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000,
        });
    };

    boot();
}

function getPayColor(payLabel) {
    switch (payLabel) {
        case "Premium":
            return "#f97316";
        case "Forte":
            return "#fb923c";
        case "Boa":
            return "#38bdf8";
        default:
            return "#22c55e";
    }
}

function haversineKm(lat1, lng1, lat2, lng2) {
    const toRadians = (value) => (value * Math.PI) / 180;
    const earthRadiusKm = 6371;
    const dLat = toRadians(lat2 - lat1);
    const dLng = toRadians(lng2 - lng1);
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRadians(lat1)) *
            Math.cos(toRadians(lat2)) *
            Math.sin(dLng / 2) *
            Math.sin(dLng / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return earthRadiusKm * c;
}
