# Smart Parking Booking System

## Overview

The Smart Parking Booking System provides a comprehensive solution for managing parking spot bookings with automatic status transitions and user-friendly controls.

## Booking Status Flow

### 1. **Pending** (Initial State)
- **When**: Booking is created but not yet activated
- **Conditions**: 
  - Payment status can be 'pending' or 'paid'
  - Start time has not been reached OR payment is pending
- **User Actions Available**:
  - Cancel booking (with refund if paid)
  - Pay for booking (if payment pending)
  - Activate booking (if payment completed and start time reached)

### 2. **Active** (In Progress)
- **When**: Booking is currently in use
- **Conditions**:
  - Payment is completed
  - Current time is between start and end time
  - Parking spot is occupied
- **User Actions Available**:
  - Cancel booking (with partial refund)
  - Extend booking time
  - Pay for extension (if applicable)

### 3. **Completed** (Finished)
- **When**: Booking time has expired
- **Conditions**:
  - End time has passed
  - Parking spot is freed
- **User Actions Available**:
  - View receipt
  - Pay outstanding amount (if any)

### 4. **Cancelled** (Terminated)
- **When**: User or system cancels the booking
- **Conditions**:
  - Booking was cancelled before or during use
  - Refund may be applicable
- **User Actions Available**:
  - View booking details (read-only)

## Automatic Status Updates

The system automatically updates booking statuses based on time and payment conditions:

### Automatic Activation
- Pending bookings automatically become **Active** when:
  - Payment status is 'paid'
  - Current time reaches or passes the start time

### Automatic Completion
- Active bookings automatically become **Completed** when:
  - Current time passes the end time
  - Parking spot is automatically freed

### Manual Activation
- Users can manually activate pending bookings if:
  - Payment is completed
  - Start time has been reached

## User Interface Features

### Booking Cards
Each booking is displayed as a card with:
- **Color-coded borders**: Different colors for each status
- **Status badges**: Clear visual indicators
- **Detailed information**: Spot, vehicle, times, amounts
- **Action buttons**: Context-sensitive based on status

### Filter System
Users can filter bookings by:
- **All Bookings**: Shows all bookings
- **Active**: Currently in-use bookings
- **Completed**: Finished bookings
- **Cancelled**: Cancelled bookings
- **Pending**: Waiting to be activated

### Action Buttons

#### For Active Bookings:
- **Cancel Booking**: Cancels with partial refund
- **Extend Time**: Adds more hours to booking
- **Pay Now**: Complete payment for extension

#### For Pending Bookings:
- **Cancel Booking**: Cancels with full refund (if paid)
- **Activate Booking**: Manually activate (if paid and time reached)
- **Pay Now**: Complete payment

#### For Completed Bookings:
- **View Receipt**: Access payment receipt
- **Pay Now**: Complete any outstanding payment

## Payment Integration

### Payment Statuses:
- **Pending**: Payment not yet completed
- **Paid**: Payment completed successfully
- **Refunded**: Payment refunded (for cancellations)

### Refund System:
- **Full Refund**: For pending bookings cancelled before activation
- **Partial Refund**: For active bookings cancelled during use
- **No Refund**: For completed bookings or unpaid cancellations

## Technical Implementation

### Database Functions

#### `updateExpiredBookings()`
- Automatically activates pending bookings when conditions are met
- Automatically completes expired active bookings
- Returns count of activated and completed bookings

#### `activateBooking($booking_id)`
- Manually activates a specific booking
- Updates parking spot occupancy
- Validates payment and time conditions

#### `calculateCurrentAmount($booking_id)`
- Calculates current amount for active bookings
- Based on actual time used vs. booked time

### Cron Job Integration

The system includes a cron job script (`update-bookings-cron.php`) that:
- Runs automatically to update booking statuses
- Logs all status changes
- Identifies overdue and expiring bookings
- Can be scheduled to run every minute

### Setup Instructions

1. **Database**: Ensure all tables are created with proper indexes
2. **Cron Job**: Set up the cron job to run every minute:
   ```bash
   * * * * * /usr/bin/php /path/to/project/update-bookings-cron.php
   ```
3. **Permissions**: Ensure proper file permissions for logging

## Error Handling

The system includes comprehensive error handling:
- **Transaction rollback**: On database errors
- **User feedback**: Clear success/error messages
- **Logging**: All operations are logged for debugging
- **Validation**: Input validation for all user actions

## Security Features

- **User verification**: All actions verify booking ownership
- **SQL injection protection**: Prepared statements throughout
- **Session management**: Proper session handling
- **Input sanitization**: All user inputs are sanitized

## Future Enhancements

Potential improvements for the booking system:
- Email notifications for status changes
- SMS alerts for expiring bookings
- Mobile app integration
- Advanced analytics and reporting
- Multi-location support
- Dynamic pricing based on demand
