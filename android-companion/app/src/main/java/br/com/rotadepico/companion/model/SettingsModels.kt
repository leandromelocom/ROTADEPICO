package br.com.rotadepico.companion.model

data class DecisionSettings(
    val decisionProfile: String,
    val minOfferFare: Double,
    val minFarePerKm: Double,
    val minHourlyRate: Double,
    val maxPickupDistanceKm: Double,
    val maxPickupEtaMinutes: Int
)

data class CostSettings(
    val fuelConsumptionKmPerL: Double,
    val fuelPricePerLiter: Double,
    val extraCostPerKm: Double
)

data class DriverSettings(
    val decisionSettings: DecisionSettings,
    val costSettings: CostSettings
)
