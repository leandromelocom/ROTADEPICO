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
        let zones = JSON.parse(mapElement.dataset.opportunityMap || "[]");
        const defaultLat = Number.parseFloat(mapElement.dataset.mapDefaultLat);
        const defaultLng = Number.parseFloat(mapElement.dataset.mapDefaultLng);
        const defaultZoom = Number.parseInt(mapElement.dataset.mapDefaultZoom, 10);
        const locationEndpoint = mapElement.dataset.locationEndpoint;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
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

        let zoneMarkers = [];
        let driverMarker = null;

        const removeMarkers = () => {
            zoneMarkers.forEach((zone) => {
                map.removeLayer(zone.marker);
                map.removeLayer(zone.hotspotCircle);
            });

            zoneMarkers = [];
        };

        const renderMarkers = (zonesToRender) => {
            removeMarkers();

            zoneMarkers = zonesToRender.map((zone) => {
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

            if (zoneMarkers.length) {
                const bounds = L.latLngBounds(zoneMarkers.map((zone) => [zone.latitude, zone.longitude]));
                map.fitBounds(bounds, { padding: [32, 32] });
            }
        };

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
                    zone.marker?.openPopup();
                    zone.hotspotCircle?.openPopup();
                });

                zoneList.appendChild(item);
            });

            if (latlng && driverCard && driverTitle && driverCopy && sortedZones[0]) {
                driverCard.hidden = false;
                driverTitle.textContent = `Voce esta em ${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)}`;
                driverCopy.textContent = `A melhor zona perto de voce agora e ${sortedZones[0].zone_name}. Toque nas sugestoes para centralizar o mapa.`;
            }
        };

        const updateLiveInsights = (payload) => {
            const bestNow = payload.bestNow || null;

            if (bestNow) {
                setText("[data-live-best-score]", `Score ${bestNow.predicted_score}`);
                setText("[data-live-ticket]", `R$ ${Number(bestNow.avg_fare).toFixed(2).replace(".", ",")}`);
                setText("[data-live-window]", bestNow.best_window);
                setText("[data-live-fit]", `${bestNow.fit_score}%`);
                setText("[data-live-hourly]", `R$ ${Number(bestNow.expected_hourly).toFixed(2).replace(".", ",")}`);
                setText("[data-live-recommendation]", bestNow.recommendation);
                setText("[data-live-now-zone]", bestNow.zone_name);
                setText("[data-live-now-priority]", bestNow.distance_km ? `${bestNow.distance_km.toFixed(1)} km` : "zona lider");
                setText("[data-live-now-copy]", bestNow.recommendation);
                setText("[data-live-distance]", bestNow.distance_km ? `${bestNow.distance_km.toFixed(1)} km` : "GPS livre");

                const signalsContainer = document.querySelector("[data-live-signals]");
                if (signalsContainer) {
                    signalsContainer.innerHTML = "";
                    (bestNow.signals || []).forEach((signal) => {
                        const chip = document.createElement("span");
                        chip.className = "chip";
                        chip.textContent = signal;
                        signalsContainer.appendChild(chip);
                    });
                }
            }

            renderCardList("[data-nearby-comparisons]", payload.nearbyComparisons || [], (zone) => `
                <article class="feature-card">
                    <div class="spread-row">
                        <div>
                            <p class="metric-label">${zone.distance_km ? zone.distance_km.toFixed(1).replace(".", ",") + " km" : "sem gps"}</p>
                            <h3 class="card-title">${zone.zone_name}</h3>
                        </div>
                        <span class="score-badge">R$ ${Number(zone.avg_fare).toFixed(2).replace(".", ",")}</span>
                    </div>
                    <p class="profile-copy">${zone.reason}</p>
                    <div class="detail-row">
                        <span>${zone.best_window}</span>
                        <span class="sky">Score local ${zone.localized_priority}</span>
                    </div>
                </article>
            `);

            renderCardList("[data-hourly-rankings]", payload.hourlyRankings || [], (slot) => `
                <article class="timeline-card">
                    <p class="metric-label">${slot.label}</p>
                    <h3 class="card-title">${slot.zone_name}</h3>
                    <p class="profile-copy">${slot.best_window}</p>
                    <div class="detail-row">
                        <span>Score ${slot.predicted_score}</span>
                        <span class="sky">R$ ${Number(slot.expected_hourly).toFixed(2).replace(".", ",")}/h</span>
                    </div>
                </article>
            `);

            renderCardList("[data-shift-forecasts]", payload.shiftForecasts || [], (slot) => `
                <article class="forecast-card">
                    <p class="metric-label">${slot.shift}</p>
                    <h3 class="card-title">${slot.zone_name}</h3>
                    <p class="profile-copy">${slot.best_window}</p>
                    <div class="detail-row">
                        <span>R$ ${Number(slot.expected_hourly).toFixed(2).replace(".", ",")}/h</span>
                        <span class="sky">Turno ${slot.shift}</span>
                    </div>
                </article>
            `);
        };

        const fetchLocalizedInsights = async (latlng) => {
            if (!locationEndpoint || !csrfToken) {
                return null;
            }

            const response = await fetch(locationEndpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    latitude: latlng.lat,
                    longitude: latlng.lng,
                }),
            });

            if (!response.ok) {
                throw new Error("radar-location-request-failed");
            }

            return response.json();
        };

        const sortByDistanceAndScore = (latlng, sourceZones) => {
            return sourceZones
                .map((zone) => {
                    const distanceKm = zone.distance_km ?? haversineKm(latlng.lat, latlng.lng, zone.latitude, zone.longitude);
                    const distancePenalty = Math.min(distanceKm * 2.8, 30);
                    const localPriority = zone.localized_priority ?? (zone.predicted_score - distancePenalty);

                    return {
                        ...zone,
                        distance_km: distanceKm,
                        local_priority: localPriority,
                    };
                })
                .sort((left, right) => right.local_priority - left.local_priority);
        };

        const handleSuccess = async (position) => {
            const latlng = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            };

            if (driverMarker) {
                map.removeLayer(driverMarker);
            }

            driverMarker = L.circleMarker([latlng.lat, latlng.lng], {
                radius: 10,
                color: "#f97316",
                weight: 3,
                fillColor: "#fdba74",
                fillOpacity: 0.9,
            }).addTo(map);

            driverMarker.bindPopup(`<strong>${copy.userPopupTitle}</strong><br>${copy.userPopupBody}`).openPopup();
            map.flyTo([latlng.lat, latlng.lng], 13, { duration: 1.2 });

            try {
                const payload = await fetchLocalizedInsights(latlng);

                if (payload?.mapZones) {
                    zones = payload.mapZones;
                    renderMarkers(zones);
                    const sortedZones = sortByDistanceAndScore(latlng, zones);
                    renderZoneList(sortedZones, latlng);
                    updateLiveInsights(payload);
                } else {
                    const sortedZones = sortByDistanceAndScore(latlng, zones);
                    renderZoneList(sortedZones, latlng);
                }

                if (statusElement) {
                    statusElement.textContent = copy.readyStatus;
                }
            } catch (error) {
                const sortedZones = sortByDistanceAndScore(latlng, zones);
                renderZoneList(sortedZones, latlng);

                if (statusElement) {
                    statusElement.textContent = copy.readyStatus;
                }
            }
        };

        const handleError = () => {
            renderZoneList(zoneMarkers);

            if (statusElement) {
                statusElement.textContent = copy.fallbackStatus;
            }
        };

        renderMarkers(zones);

        if (statusElement) {
            statusElement.textContent = copy.loadingStatus;
        }

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

function setText(selector, text) {
    const element = document.querySelector(selector);

    if (element) {
        element.textContent = text;
    }
}

function renderCardList(selector, items, renderer) {
    const container = document.querySelector(selector);

    if (!container) {
        return;
    }

    container.innerHTML = items.map(renderer).join("");
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
