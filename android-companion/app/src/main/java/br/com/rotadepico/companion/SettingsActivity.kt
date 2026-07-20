package br.com.rotadepico.companion

import android.os.Bundle
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.EditText
import android.widget.Spinner
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import br.com.rotadepico.companion.data.SettingsRepository
import br.com.rotadepico.companion.model.CostSettings
import br.com.rotadepico.companion.model.DecisionSettings
import br.com.rotadepico.companion.network.SettingsApiClient
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class SettingsActivity : AppCompatActivity() {

    private lateinit var settingsRepository: SettingsRepository
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_settings)

        settingsRepository = SettingsRepository(this)

        val statusValue = findViewById<TextView>(R.id.settingsStatusValue)
        val backLink = findViewById<TextView>(R.id.settingsBackLink)
        val decisionProfileSpinner = findViewById<Spinner>(R.id.decisionProfileSpinner)
        val minOfferFareField = findViewById<EditText>(R.id.minOfferFareField)
        val minFarePerKmField = findViewById<EditText>(R.id.minFarePerKmField)
        val minHourlyRateField = findViewById<EditText>(R.id.minHourlyRateField)
        val maxPickupDistanceKmField = findViewById<EditText>(R.id.maxPickupDistanceKmField)
        val maxPickupEtaMinutesField = findViewById<EditText>(R.id.maxPickupEtaMinutesField)
        val saveDecisionSettingsButton = findViewById<Button>(R.id.saveDecisionSettingsButton)
        val fuelConsumptionField = findViewById<EditText>(R.id.fuelConsumptionField)
        val fuelPriceField = findViewById<EditText>(R.id.fuelPriceField)
        val extraCostPerKmField = findViewById<EditText>(R.id.extraCostPerKmField)
        val saveCostSettingsButton = findViewById<Button>(R.id.saveCostSettingsButton)

        backLink.setOnClickListener { finish() }

        val client = SettingsApiClient(settingsRepository.apiBaseUrl(), settingsRepository.bearerToken())

        if (settingsRepository.bearerToken().isBlank()) {
            statusValue.text = getString(R.string.settings_load_error)
        } else {
            scope.launch {
                runCatching {
                    withContext(Dispatchers.IO) { client.fetch() }
                }.onSuccess { settings ->
                    statusValue.text = getString(R.string.settings_ready)

                    selectSpinnerValue(decisionProfileSpinner, settings.decisionSettings.decisionProfile)
                    minOfferFareField.setText(settings.decisionSettings.minOfferFare.toString())
                    minFarePerKmField.setText(settings.decisionSettings.minFarePerKm.toString())
                    minHourlyRateField.setText(settings.decisionSettings.minHourlyRate.toString())
                    maxPickupDistanceKmField.setText(settings.decisionSettings.maxPickupDistanceKm.toString())
                    maxPickupEtaMinutesField.setText(settings.decisionSettings.maxPickupEtaMinutes.toString())

                    fuelConsumptionField.setText(settings.costSettings.fuelConsumptionKmPerL.toString())
                    fuelPriceField.setText(settings.costSettings.fuelPricePerLiter.toString())
                    extraCostPerKmField.setText(settings.costSettings.extraCostPerKm.toString())
                }.onFailure {
                    statusValue.text = it.message ?: getString(R.string.settings_load_error)
                }
            }
        }

        saveDecisionSettingsButton.setOnClickListener {
            val settings = DecisionSettings(
                decisionProfile = decisionProfileSpinner.selectedItem?.toString().orEmpty(),
                minOfferFare = minOfferFareField.text.toString().toDoubleOrNull() ?: 0.0,
                minFarePerKm = minFarePerKmField.text.toString().toDoubleOrNull() ?: 0.0,
                minHourlyRate = minHourlyRateField.text.toString().toDoubleOrNull() ?: 0.0,
                maxPickupDistanceKm = maxPickupDistanceKmField.text.toString().toDoubleOrNull() ?: 0.0,
                maxPickupEtaMinutes = maxPickupEtaMinutesField.text.toString().toIntOrNull() ?: 0
            )

            statusValue.text = getString(R.string.decision_settings_saving)

            scope.launch {
                runCatching {
                    withContext(Dispatchers.IO) { client.updateDecisionSettings(settings) }
                }.onSuccess {
                    statusValue.text = getString(R.string.decision_settings_saved)
                    Toast.makeText(this@SettingsActivity, R.string.decision_settings_saved, Toast.LENGTH_LONG).show()
                }.onFailure {
                    val errorMessage = it.message ?: getString(R.string.decision_settings_error)
                    statusValue.text = errorMessage
                    Toast.makeText(this@SettingsActivity, errorMessage, Toast.LENGTH_LONG).show()
                }
            }
        }

        saveCostSettingsButton.setOnClickListener {
            val settings = CostSettings(
                fuelConsumptionKmPerL = fuelConsumptionField.text.toString().toDoubleOrNull() ?: 0.0,
                fuelPricePerLiter = fuelPriceField.text.toString().toDoubleOrNull() ?: 0.0,
                extraCostPerKm = extraCostPerKmField.text.toString().toDoubleOrNull() ?: 0.0
            )

            statusValue.text = getString(R.string.cost_settings_saving)

            scope.launch {
                runCatching {
                    withContext(Dispatchers.IO) { client.updateCostSettings(settings) }
                }.onSuccess {
                    statusValue.text = getString(R.string.cost_settings_saved)
                    Toast.makeText(this@SettingsActivity, R.string.cost_settings_saved, Toast.LENGTH_LONG).show()
                }.onFailure {
                    val errorMessage = it.message ?: getString(R.string.cost_settings_error)
                    statusValue.text = errorMessage
                    Toast.makeText(this@SettingsActivity, errorMessage, Toast.LENGTH_LONG).show()
                }
            }
        }
    }

    private fun selectSpinnerValue(spinner: Spinner, value: String) {
        val adapter = spinner.adapter as? ArrayAdapter<*> ?: return
        val position = (0 until adapter.count).firstOrNull { adapter.getItem(it) == value }
        if (position != null) {
            spinner.setSelection(position)
        }
    }
}
