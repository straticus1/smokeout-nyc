import nodemailer from 'nodemailer';

interface EmailOptions {
  to: string;
  subject: string;
  text?: string;
  html?: string;
}

// Create transporter
const transporter = nodemailer.createTransporter({
  host: process.env.EMAIL_HOST || 'smtp.gmail.com',
  port: parseInt(process.env.EMAIL_PORT || '587'),
  secure: false, // true for 465, false for other ports
  auth: {
    user: process.env.EMAIL_USER,
    pass: process.env.EMAIL_PASS
  }
});

export const sendEmail = async (options: EmailOptions): Promise<void> => {
  const mailOptions = {
    from: process.env.EMAIL_FROM || 'noreply@smokeout.nyc',
    to: options.to,
    subject: options.subject,
    text: options.text,
    html: options.html
  };

  try {
    await transporter.sendMail(mailOptions);
    console.log('Email sent successfully to:', options.to);
  } catch (error) {
    console.error('Failed to send email:', error);
    throw error;
  }
};

export const sendBulkEmail = async (recipients: string[], subject: string, html: string): Promise<void> => {
  const promises = recipients.map(recipient => 
    sendEmail({ to: recipient, subject, html })
  );
  
  try {
    await Promise.all(promises);
    console.log(`Bulk email sent to ${recipients.length} recipients`);
  } catch (error) {
    console.error('Failed to send bulk email:', error);
    throw error;
  }
};
