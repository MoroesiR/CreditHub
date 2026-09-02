/**
 * Reads a South African ID number: YYMMDD | SSSS | C | A | Z.
 *
 * Mirrors the server's App\Support\IdentityNumber so the officer sees the date
 * of birth, age and gender as they type. The values sent on save are the
 * server's own - this is feedback at the desk, not the record.
 */

export interface IdentityDetails {
  dateOfBirth: Date
  age: number
  gender: 'female' | 'male'
}

export interface IdInspection {
  details: IdentityDetails | null
  /** Why nothing could be read, once enough digits are present to judge. */
  problem: string | null
}

/**
 * Parsing alone is not enough for the form: when a number cannot be read the
 * officer needs to know which part of it is wrong, or the fields simply sit
 * blank and the screen looks broken.
 */
export function inspectIdNumber(value: string): IdInspection {
  const digits = value.trim()

  if (digits.length === 0) {
    return { details: null, problem: null }
  }

  if (!/^\d+$/.test(digits)) {
    return { details: null, problem: 'An ID number is digits only.' }
  }

  // Still being typed: say nothing rather than flag a half-entered number.
  if (digits.length < 13) {
    return { details: null, problem: null }
  }

  if (digits.length > 13) {
    return { details: null, problem: 'An ID number is exactly 13 digits.' }
  }

  if (dateOfBirthFrom(digits) === null) {
    return {
      details: null,
      problem: 'The first six digits are not a real date of birth.',
    }
  }

  if (!hasValidCheckDigit(digits)) {
    return {
      details: null,
      problem: 'That is not a valid ID number - the last digit does not check out. Check for a typo.',
    }
  }

  return { details: parseIdNumber(digits), problem: null }
}

export function parseIdNumber(value: string): IdentityDetails | null {
  if (!/^\d{13}$/.test(value) || !hasValidCheckDigit(value)) {
    return null
  }

  const dateOfBirth = dateOfBirthFrom(value)

  if (dateOfBirth === null) {
    return null
  }

  return {
    dateOfBirth,
    age: ageOn(dateOfBirth),
    // 0000-4999 female, 5000-9999 male.
    gender: Number(value.slice(6, 10)) < 5000 ? 'female' : 'male',
  }
}

function dateOfBirthFrom(value: string): Date | null {
  const yy = Number(value.slice(0, 2))
  const month = Number(value.slice(2, 4))
  const day = Number(value.slice(4, 6))

  // Two digits cannot say which century; a birth that would fall in the future
  // belongs to the previous one.
  const century = new Date().getFullYear() % 100 >= yy ? 2000 : 1900
  const date = new Date(century + yy, month - 1, day)

  // The Date constructor rolls 31 February forward into March, so the round
  // trip is what actually rejects an impossible date.
  const isRealDate =
    date.getFullYear() === century + yy &&
    date.getMonth() === month - 1 &&
    date.getDate() === day

  return isRealDate ? date : null
}

function ageOn(birth: Date, today = new Date()): number {
  let age = today.getFullYear() - birth.getFullYear()
  const monthDelta = today.getMonth() - birth.getMonth()

  if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birth.getDate())) {
    age--
  }

  return age
}

/**
 * Luhn over the first twelve digits.
 *
 * The doubling starts ON the twelfth digit - the one immediately left of the
 * check digit - not on the one before it. Getting that parity wrong still
 * produces a self-consistent checksum, so generated numbers validate against
 * each other while every real ID is rejected.
 */
export function hasValidCheckDigit(value: string): boolean {
  if (!/^\d{13}$/.test(value)) {
    return false
  }

  let sum = 0
  let double = true

  for (let i = 11; i >= 0; i--) {
    let digit = Number(value[i])

    if (double) {
      digit *= 2

      if (digit > 9) {
        digit -= 9
      }
    }

    sum += digit
    double = !double
  }

  return (10 - (sum % 10)) % 10 === Number(value[12])
}
