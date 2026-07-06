package br.com.rotadepico.companion.model

data class MobileAuthResponse(
    val token: String,
    val tokenType: String,
    val user: MobileUser
)

data class MobileUser(
    val name: String,
    val email: String,
    val city: String?,
    val vehicleType: String?,
    val workShift: String?
)
