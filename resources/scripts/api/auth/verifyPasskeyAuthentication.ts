import { AuthenticationResponseJSON } from '@simplewebauthn/types'

import axios from '@/lib/axios.ts'


const verifyPasskeyAuthentication = async (
    authResponse: AuthenticationResponseJSON
) => {
    await axios.post('/api/auth/passkeys/verify-authentication', {
        ...authResponse,
    })
}

export default verifyPasskeyAuthentication
